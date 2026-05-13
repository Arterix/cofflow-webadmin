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
            if ((float) $info['ingredient']->current_stock < $info['needed']) {
                throw ValidationException::withMessages([
                    'stock' => ["Stok {$info['ingredient']->name} tidak mencukupi untuk membuat {$info['menu_name']}"],
                ]);
            }
        }
    }

    /**
     * Deduct stock for an order based on its items' menus' BOMs.
     *
     * Returns ingredients that crossed below minimum_stock during deduction.
     *
     * @return array<int, Ingredient>
     */
    public function deductForOrder(Order $order): array
    {
        $criticalNow = [];

        foreach ($order->items as $orderItem) {
            $menu = Menu::with('bomItems.ingredient')->find($orderItem->menu_id);
            if (! $menu) {
                continue;
            }

            foreach ($menu->bomItems as $bom) {
                $ingredient = $bom->ingredient;
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

    public function restoreForOrder(Order $order): void
    {
        foreach ($order->items as $orderItem) {
            $menu = Menu::with('bomItems.ingredient')->find($orderItem->menu_id);
            if (! $menu) {
                continue;
            }

            foreach ($menu->bomItems as $bom) {
                $ingredient = $bom->ingredient;
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

        foreach ($items as $item) {
            $menu = Menu::with('bomItems.ingredient')->find($item['menu_id']);
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
