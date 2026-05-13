<?php

namespace App\Services;

use App\Models\EventDiscount;
use App\Models\Menu;
use App\Models\ProductDiscount;
use App\Models\PromoCode;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class DiscountService
{
    public function getActiveProductDiscount(int $menuId, ?Carbon $date = null): ?ProductDiscount
    {
        $date ??= Carbon::today();

        return ProductDiscount::where('menu_id', $menuId)
            ->where('is_active', true)
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->orderByDesc('value')
            ->first();
    }

    public function getActiveEventDiscount(int $menuId, ?Carbon $date = null): ?EventDiscount
    {
        $date ??= Carbon::today();

        return EventDiscount::whereHas('menus', fn ($q) => $q->where('menus.id', $menuId))
            ->where('is_active', true)
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->first();
    }

    /**
     * Returns the per-unit discount amount applied to the menu, taking the larger of product/event discount.
     */
    public function calculatePerUnitDiscount(Menu $menu, ?Carbon $date = null): float
    {
        $date ??= Carbon::today();
        $price = (float) $menu->price;

        $product = $this->getActiveProductDiscount($menu->id, $date);
        $event = $this->getActiveEventDiscount($menu->id, $date);

        $productAmount = $product ? $this->amountFromRule($product->type, (float) $product->value, $price) : 0.0;
        $eventAmount = $event ? $this->amountFromRule($event->type, (float) $event->value, $price) : 0.0;

        return max($productAmount, $eventAmount);
    }

    public function validateAndCalcPromo(string $code, float $subtotal): array
    {
        $today = Carbon::today();

        $promo = PromoCode::where('code', $code)
            ->where('is_active', true)
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->first();

        if (! $promo) {
            throw ValidationException::withMessages([
                'promo_code' => ['Kode promo tidak valid atau sudah tidak berlaku'],
            ]);
        }

        if ($promo->used_count >= $promo->max_usage) {
            throw ValidationException::withMessages([
                'promo_code' => ['Kode promo sudah habis digunakan'],
            ]);
        }

        if ($subtotal < (float) $promo->min_order) {
            throw ValidationException::withMessages([
                'promo_code' => ['Minimum order Rp '.number_format((float) $promo->min_order, 0, ',', '.')],
            ]);
        }

        $amount = $this->amountFromRule($promo->type, (float) $promo->value, $subtotal);

        return ['promo' => $promo, 'amount' => $amount];
    }

    /**
     * @param  array<int, array{menu_id:int, quantity:int, condiment_option_ids?:array<int>}>  $items
     */
    public function calculateOrderTotal(array $items, ?string $promoCode = null): array
    {
        $today = Carbon::today();
        $subtotal = 0.0;
        $totalItemDiscount = 0.0;
        $itemBreakdown = [];

        foreach ($items as $item) {
            $menu = Menu::find($item['menu_id']);
            if (! $menu) {
                throw ValidationException::withMessages([
                    'items' => ["Menu ID {$item['menu_id']} tidak ditemukan"],
                ]);
            }

            $quantity = (int) $item['quantity'];
            $unitPrice = (float) $menu->price;
            $perUnitDiscount = $this->calculatePerUnitDiscount($menu, $today);
            $itemTotal = ($unitPrice - $perUnitDiscount) * $quantity;

            $condimentTotal = 0.0;
            if (! empty($item['condiment_option_ids'])) {
                $condimentTotal = (float) \App\Models\CondimentOption::whereIn('id', $item['condiment_option_ids'])
                    ->sum('additional_price') * $quantity;
            }

            $subtotal += $itemTotal + $condimentTotal;
            $totalItemDiscount += $perUnitDiscount * $quantity;

            $itemBreakdown[] = [
                'menu' => $menu,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'applied_discount_per_unit' => $perUnitDiscount,
                'condiment_total' => $condimentTotal,
                'line_subtotal' => $itemTotal + $condimentTotal,
            ];
        }

        $promoDiscount = 0.0;
        $promoModel = null;
        if ($promoCode) {
            $result = $this->validateAndCalcPromo($promoCode, $subtotal);
            $promoDiscount = $result['amount'];
            $promoModel = $result['promo'];
        }

        $total = max(0, $subtotal - $promoDiscount);
        $discountAmount = $totalItemDiscount + $promoDiscount;

        return [
            'subtotal' => round($subtotal, 2),
            'discount_amount' => round($discountAmount, 2),
            'item_discount' => round($totalItemDiscount, 2),
            'promo_discount' => round($promoDiscount, 2),
            'total' => round($total, 2),
            'item_breakdown' => $itemBreakdown,
            'promo' => $promoModel,
        ];
    }

    private function amountFromRule(string $type, float $value, float $base): float
    {
        if ($type === 'percentage') {
            return $base * ($value / 100);
        }

        return min($value, $base);
    }
}
