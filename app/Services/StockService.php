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
     * Validate stock for the requested items. Throws ValidationException if any ingredient is insufficient.
     *
     * @param  array<int, array{menu_id:int, quantity:int}>  $items
     */
    public function validateStock(array $items): void
    {
        $needed = $this->aggregateRequirements($items);

        foreach ($needed as $ingredientId => $info) {
            // Re-read with lock when called inside a transaction for accuracy
            $freshStock = (float) $info['ingredient']->current_stock;
            if ($freshStock < $info['needed']) {
                throw ValidationException::withMessages([
                    'stock' => ["Stok {$info['ingredient']->name} tidak mencukupi untuk membuat {$info['menu_name']}"],
                ]);
            }
        }
    }

    /**
     * Deduct stock for an order based on its items' menus' BOMs.
     *
     * Must be called inside a DB::transaction() for lock safety.
     * Returns ingredients that crossed below minimum_stock during deduction.
     *
     * @return array<int, Ingredient>
     */
    public function deductForOrder(Order $order): array
    {
        $criticalNow = [];

        // Batch-load all menus with their BOMs in a single query (fixes N+1)
        $menuIds = $order->items->pluck('menu_id')->unique()->toArray();
        $menus = Menu::with('bomItems.ingredient')
            ->whereIn('id', $menuIds)
            ->get()
            ->keyBy('id');

        // Collect all ingredient IDs needed, then lock them all at once (fixes race condition)
        $ingredientIds = $menus->flatMap(fn ($menu) => $menu->bomItems->pluck('ingredient_id'))
            ->unique()
            ->filter()
            ->toArray();
        $lockedIngredients = Ingredient::whereIn('id', $ingredientIds)
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($order->items as $orderItem) {
            $menu = $menus->get($orderItem->menu_id);
            if (! $menu) {
                continue;
            }

            foreach ($menu->bomItems as $bom) {
                $ingredient = $lockedIngredients->get($bom->ingredient_id);
                if (! $ingredient) {
                    continue;
                }
                $needed = (float) $bom->quantity * (int) $orderItem->quantity;
                $wasCritical = (float) $ingredient->current_stock < (float) $ingredient->minimum_stock;
                $ingredient->current_stock = max(0, (float) $ingredient->current_stock - $needed);
                $ingredient->save();

                if (! $wasCritical && (float) $ingredient->current_stock < (float) $ingredient->minimum_stock) {
                    $criticalNow[$ingredient->id] = $ingredient;
                }
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

            foreach ($opname->items as $item) {
                $ingredient = $item->ingredient;
                if (! $ingredient) {
                    continue;
                }
                $ingredient->current_stock = (float) $item->physical_stock;
                $ingredient->save();
            }
        });
    }

    /**
     * Restore stock for a cancelled order. Must be called inside a DB::transaction().
     */
    public function restoreForOrder(Order $order): void
    {
        // Batch-load all menus with their BOMs in a single query (fixes N+1)
        $menuIds = $order->items->pluck('menu_id')->unique()->toArray();
        $menus = Menu::with('bomItems.ingredient')
            ->whereIn('id', $menuIds)
            ->get()
            ->keyBy('id');

        // Lock all affected ingredients to prevent concurrent modification
        $ingredientIds = $menus->flatMap(fn ($menu) => $menu->bomItems->pluck('ingredient_id'))
            ->unique()
            ->filter()
            ->toArray();
        $lockedIngredients = Ingredient::whereIn('id', $ingredientIds)
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($order->items as $orderItem) {
            $menu = $menus->get($orderItem->menu_id);
            if (! $menu) {
                continue;
            }

            foreach ($menu->bomItems as $bom) {
                $ingredient = $lockedIngredients->get($bom->ingredient_id);
                if (! $ingredient) {
                    continue;
                }
                $amount = (float) $bom->quantity * (int) $orderItem->quantity;
                $ingredient->current_stock = (float) $ingredient->current_stock + $amount;
                $ingredient->save();
            }
        }
    }

    /**
     * @param  array<int, array{menu_id:int, quantity:int}>  $items
     * @return array<int, array{ingredient: Ingredient, needed: float, menu_name: string}>
     */
    private function aggregateRequirements(array $items): array
    {
        $needed = [];

        // Batch-load all menus with their BOMs in a single query (fixes N+1)
        $menuIds = array_column($items, 'menu_id');
        $menus = Menu::with('bomItems.ingredient')
            ->whereIn('id', $menuIds)
            ->get()
            ->keyBy('id');

        foreach ($items as $item) {
            $menu = $menus->get($item['menu_id']);
            if (! $menu) {
                throw ValidationException::withMessages([
                    'items' => ["Menu dengan ID {$item['menu_id']} tidak ditemukan"],
                ]);
            }

            foreach ($menu->bomItems as $bom) {
                $ingredient = $bom->ingredient;
                if (! $ingredient) {
                    continue;
                }
                $qty = (float) $bom->quantity * (int) $item['quantity'];
                if (! isset($needed[$ingredient->id])) {
                    $needed[$ingredient->id] = [
                        'ingredient' => $ingredient,
                        'needed' => 0.0,
                        'menu_name' => $menu->name,
                    ];
                }
                $needed[$ingredient->id]['needed'] += $qty;
            }
        }

        return $needed;
    }
}
