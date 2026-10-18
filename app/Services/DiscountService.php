<?php

namespace App\Services;

use App\Models\EventDiscount;
use App\Models\Menu;
use App\Models\ProductDiscount;
use App\Models\PromoCode;
use App\Models\UserVoucher;
use Carbon\Carbon;
use Illuminate\Support\Collection;
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
    public function calculateOrderTotal(array $items, ?string $promoCode = null, ?UserVoucher $voucher = null): array
    {
        if ($promoCode && $voucher) {
            throw ValidationException::withMessages([
                'voucher_id' => ['Pilih salah satu: voucher poin atau kode promo'],
            ]);
        }

        $today = Carbon::today();
        $subtotal = 0.0;
        $totalItemDiscount = 0.0;
        $itemBreakdown = [];

        // Batch-load all menus in a single query (fixes N+1)
        $menuIds = array_column($items, 'menu_id');
        $menus = Menu::whereIn('id', $menuIds)->get()->keyBy('id');

        // Batch-load all active product discounts for these menus (fixes N+1)
        $productDiscounts = ProductDiscount::whereIn('menu_id', $menuIds)
            ->where('is_active', true)
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->orderByDesc('value')
            ->get()
            ->groupBy('menu_id');

        // Batch-load all active event discounts for these menus (fixes N+1)
        $eventDiscounts = $this->batchActiveEventDiscounts($menuIds, $today);

        foreach ($items as $item) {
            $menu = $menus->get($item['menu_id']);
            if (! $menu) {
                throw ValidationException::withMessages([
                    'items' => ["Menu ID {$item['menu_id']} tidak ditemukan"],
                ]);
            }

            $quantity = (int) $item['quantity'];
            $unitPrice = (float) $menu->price;

            // Calculate per-unit discount from pre-loaded collections
            $perUnitDiscount = $this->calculatePerUnitDiscountFromCollections(
                $menu,
                $productDiscounts->get($menu->id, collect()),
                $eventDiscounts->get($menu->id, collect())
            );
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

        $voucherDiscount = $voucher ? $this->calcVoucher($voucher, $subtotal) : 0.0;

        $total = max(0, $subtotal - $promoDiscount - $voucherDiscount);
        $discountAmount = $totalItemDiscount + $promoDiscount + $voucherDiscount;

        return [
            'subtotal' => round($subtotal, 2),
            'discount_amount' => round($discountAmount, 2),
            'item_discount' => round($totalItemDiscount, 2),
            'promo_discount' => round($promoDiscount, 2),
            'voucher_discount' => round($voucherDiscount, 2),
            'total' => round($total, 2),
            'item_breakdown' => $itemBreakdown,
            'promo' => $promoModel,
            'voucher' => $voucher,
        ];
    }

    public function calcVoucher(UserVoucher $voucher, float $subtotal): float
    {
        if ($subtotal < (float) $voucher->min_order) {
            throw ValidationException::withMessages([
                'voucher_id' => ['Voucher butuh minimum order Rp '.number_format((float) $voucher->min_order, 0, ',', '.')],
            ]);
        }

        $amount = $this->amountFromRule($voucher->type, (float) $voucher->value, $subtotal);
        if ($voucher->max_discount !== null) {
            $amount = min($amount, (float) $voucher->max_discount);
        }

        return $amount;
    }

    private function amountFromRule(string $type, float $value, float $base): float
    {
        if ($type === 'percentage') {
            // Hard-cap at 100% so a bad DB value can never produce a negative subtotal
            $safePct = min($value, 100.0);
            return $base * ($safePct / 100);
        }

        // Nominal: cannot exceed the base price (prevents negative line totals)
        return min($value, $base);
    }

    /**
     * Calculate per-unit discount from pre-loaded discount collections (avoids per-item queries).
     */
    private function calculatePerUnitDiscountFromCollections(
        Menu $menu,
        Collection $productDiscounts,
        Collection $eventDiscounts
    ): float {
        $price = (float) $menu->price;

        // Product discounts are already sorted by value desc, take the first (highest)
        $product = $productDiscounts->first();
        $event = $eventDiscounts->first();

        $productAmount = $product ? $this->amountFromRule($product->type, (float) $product->value, $price) : 0.0;
        $eventAmount = $event ? $this->amountFromRule($event->type, (float) $event->value, $price) : 0.0;

        return max($productAmount, $eventAmount);
    }

    /**
     * Batch-load active event discounts for multiple menu IDs.
     *
     * @param  array<int>  $menuIds
     * @return Collection  Keyed by menu_id
     */
    private function batchActiveEventDiscounts(array $menuIds, Carbon $date): Collection
    {
        $eventDiscounts = EventDiscount::whereHas('menus', fn ($q) => $q->whereIn('menus.id', $menuIds))
            ->where('is_active', true)
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->with(['menus' => fn ($q) => $q->whereIn('menus.id', $menuIds)])
            ->get();

        // Group by menu_id for O(1) lookup
        $grouped = collect();
        foreach ($eventDiscounts as $discount) {
            foreach ($discount->menus as $menu) {
                if (! $grouped->has($menu->id)) {
                    $grouped[$menu->id] = collect();
                }
                $grouped[$menu->id]->push($discount);
            }
        }

        return $grouped;
    }
}
