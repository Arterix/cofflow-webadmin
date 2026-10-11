<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\Menu;
use App\Models\Order;
use App\Models\StockOpname;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockService
{
    /**
     * Validate AND deduct stock for an order atomically.
     *
     * Must be called INSIDE a DB::transaction(). This method:
     *   1. Batch-loads all menus + BOMs AND condiment option BOMs for the order items.
     *   2. Locks all affected ingredients with SELECT ... FOR UPDATE to prevent
     *      concurrent overselling (race condition).
     *   3. Re-reads stock from the locked rows and throws ValidationException
     *      if any ingredient is insufficient — the transaction will roll back.
     *   4. Deducts stock from the locked rows. No max(0,...) masking — if the
     *      data is consistent, stock must never go below zero here.
     *
     * Returns ingredients that crossed below minimum_stock during deduction
     * (used to trigger FCM alerts after the transaction commits).
     *
     * @param  Order  $order  Must have items and condiments loaded.
     * @return array<int, Ingredient>
     */
    public function validateAndDeductForOrder(Order $order): array
    {
        $order->loadMissing(['items.condiments.condimentOption.bomItems']);

        // 1. Batch-load all menus + BOMs in a single query (avoids N+1)
        $menuIds = $order->items->pluck('menu_id')->unique()->toArray();
        $menus = Menu::with('bomItems')
            ->whereIn('id', $menuIds)
            ->get()
            ->keyBy('id');

        // 2. Collect all ingredient IDs needed across all items (menus + condiments)
        $menuIngredientIds = $menus
            ->flatMap(fn ($menu) => $menu->bomItems->pluck('ingredient_id'));

        $condimentIngredientIds = $order->items
            ->flatMap(fn ($item) => $item->condiments->flatMap(
                fn ($c) => $c->condimentOption?->bomItems->pluck('ingredient_id') ?? []
            ));

        $ingredientIds = $menuIngredientIds
            ->concat($condimentIngredientIds)
            ->unique()
            ->filter()
            ->toArray();

        if (empty($ingredientIds)) {
            return []; // No BOM configured — nothing to deduct
        }

        // 3. Lock all affected ingredients at once (prevents concurrent race)
        $lockedIngredients = Ingredient::whereIn('id', $ingredientIds)
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        // 4. Aggregate total needed per ingredient across all order items
        $needed = []; // [ingredient_id => ['needed' => float, 'menu_name' => string]]
        foreach ($order->items as $orderItem) {
            $menu = $menus->get($orderItem->menu_id);
            if (! $menu) {
                continue;
            }

            // A. Menu BOM items
            foreach ($menu->bomItems as $bom) {
                $ingredientId = $bom->ingredient_id;
                if (! $lockedIngredients->has($ingredientId)) {
                    continue;
                }
                $qty = (float) $bom->quantity * (int) $orderItem->quantity;
                if (! isset($needed[$ingredientId])) {
                    $needed[$ingredientId] = ['needed' => 0.0, 'menu_name' => $menu->name];
                }
                $needed[$ingredientId]['needed'] += $qty;
            }

            // B. Condiment option BOM items
            foreach ($orderItem->condiments as $condiment) {
                $condimentOption = $condiment->condimentOption;
                if (! $condimentOption) {
                    continue;
                }

                foreach ($condimentOption->bomItems as $condimentBom) {
                    $ingredientId = $condimentBom->ingredient_id;
                    if (! $lockedIngredients->has($ingredientId)) {
                        continue;
                    }
                    $qty = (float) $condimentBom->quantity * (int) $orderItem->quantity;
                    if (! isset($needed[$ingredientId])) {
                        $needed[$ingredientId] = [
                            'needed' => 0.0,
                            'menu_name' => "{$menu->name} ({$condiment->option_name})",
                        ];
                    }
                    $needed[$ingredientId]['needed'] += $qty;
                }
            }
        }

        // 5. Check sufficiency from LOCKED data — throw if any ingredient is short
        foreach ($needed as $ingredientId => $info) {
            $ingredient = $lockedIngredients->get($ingredientId);
            if ((float) $ingredient->current_stock < $info['needed']) {
                throw ValidationException::withMessages([
                    'stock' => ["Stok {$ingredient->name} tidak mencukupi untuk membuat {$info['menu_name']}"],
                ]);
            }
        }

        // 6. Deduct from locked rows and collect newly-critical ingredients
        $criticalNow = [];
        foreach ($needed as $ingredientId => $info) {
            $ingredient = $lockedIngredients->get($ingredientId);
            $wasCritical = (float) $ingredient->current_stock < (float) $ingredient->minimum_stock;

            $ingredient->current_stock = (float) $ingredient->current_stock - $info['needed'];
            $ingredient->save();

            if (! $wasCritical && (float) $ingredient->current_stock < (float) $ingredient->minimum_stock) {
                $criticalNow[$ingredientId] = $ingredient;
            }
        }

        return array_values($criticalNow);
    }

    /**
     * Apply approved opname: overwrite ingredients.current_stock with physical counts.
     * Wrapped in a DB transaction so all updates land or none do.
     */
    public function applyOpnameAdjustment(StockOpname $opname): void
    {
        DB::transaction(function () use ($opname) {
            $opname->loadMissing('items.ingredient');

            // Lock all affected ingredients before overwriting
            $ingredientIds = $opname->items->pluck('ingredient_id')->filter()->toArray();
            $lockedIngredients = Ingredient::whereIn('id', $ingredientIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($opname->items as $item) {
                $ingredient = $lockedIngredients->get($item->ingredient_id);
                if (! $ingredient) {
                    continue;
                }
                $ingredient->current_stock = (float) $item->physical_stock;
                $ingredient->save();
            }
        });
    }

    /**
     * Restore stock for a cancelled order (including condiment BOMs).
     * Must be called inside a DB::transaction().
     * Idempotent: safe against duplicate calls via stock_restored_at guard.
     */
    public function restoreForOrder(Order $order): void
    {
        if ($order->stock_restored_at !== null) {
            return; // Already restored
        }

        $order->loadMissing(['items.condiments.condimentOption.bomItems']);

        $menuIds = $order->items->pluck('menu_id')->unique()->toArray();
        $menus = Menu::with('bomItems')
            ->whereIn('id', $menuIds)
            ->get()
            ->keyBy('id');

        $menuIngredientIds = $menus
            ->flatMap(fn ($menu) => $menu->bomItems->pluck('ingredient_id'));

        $condimentIngredientIds = $order->items
            ->flatMap(fn ($item) => $item->condiments->flatMap(
                fn ($c) => $c->condimentOption?->bomItems->pluck('ingredient_id') ?? []
            ));

        $ingredientIds = $menuIngredientIds
            ->concat($condimentIngredientIds)
            ->unique()
            ->filter()
            ->toArray();

        if (empty($ingredientIds)) {
            return;
        }

        // Lock ingredients before restoring to prevent concurrent modification
        $lockedIngredients = Ingredient::whereIn('id', $ingredientIds)
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        // Aggregate amounts to restore per ingredient
        $restore = [];
        foreach ($order->items as $orderItem) {
            $menu = $menus->get($orderItem->menu_id);
            if (! $menu) {
                continue;
            }

            // Menu BOM items
            foreach ($menu->bomItems as $bom) {
                $ingredientId = $bom->ingredient_id;
                if (! $lockedIngredients->has($ingredientId)) {
                    continue;
                }
                $restore[$ingredientId] = ($restore[$ingredientId] ?? 0.0)
                    + (float) $bom->quantity * (int) $orderItem->quantity;
            }

            // Condiment BOM items
            foreach ($orderItem->condiments as $condiment) {
                $condimentOption = $condiment->condimentOption;
                if (! $condimentOption) {
                    continue;
                }

                foreach ($condimentOption->bomItems as $condimentBom) {
                    $ingredientId = $condimentBom->ingredient_id;
                    if (! $lockedIngredients->has($ingredientId)) {
                        continue;
                    }
                    $restore[$ingredientId] = ($restore[$ingredientId] ?? 0.0)
                        + (float) $condimentBom->quantity * (int) $orderItem->quantity;
                }
            }
        }

        foreach ($restore as $ingredientId => $amount) {
            $ingredient = $lockedIngredients->get($ingredientId);
            $ingredient->current_stock = (float) $ingredient->current_stock + $amount;
            $ingredient->save();
        }

        $order->stock_restored_at = now();
        $order->save();
    }
}
