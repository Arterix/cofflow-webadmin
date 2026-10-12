<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\EventDiscount;
use App\Models\Menu;
use App\Models\PointReward;
use App\Models\ProductDiscount;
use App\Models\PromoCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DiscountController extends Controller
{
    private function validateDiscountValue(string $type, float $value): void
    {
        if ($type === 'percentage' && $value > 100) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'value' => ['Diskon persentase tidak boleh melebihi 100%.'],
            ]);
        }
    }

    public function index(Request $request): View
    {
        $tab = $request->query('tab', 'product');
        $menus = Menu::orderBy('name')->get();

        $products = ProductDiscount::with('menu')->orderByDesc('id')->get();
        $promos = PromoCode::orderByDesc('id')->get();
        $events = EventDiscount::with('menus')->orderByDesc('id')->get();
        $rewards = PointReward::orderBy('points_cost')->get();

        return view('admin.discounts.index', compact('tab', 'menus', 'products', 'promos', 'events', 'rewards'));
    }

    public function storeReward(Request $request): RedirectResponse
    {
        PointReward::create($this->validateReward($request) + ['is_active' => true]);

        return redirect()->route('admin.discounts.index', ['tab' => 'reward'])->with('status', 'Reward poin dibuat');
    }

    public function updateReward(Request $request, PointReward $reward): RedirectResponse
    {
        $reward->update($this->validateReward($request));

        return redirect()->route('admin.discounts.index', ['tab' => 'reward'])->with('status', 'Reward poin diperbarui');
    }

    public function toggleReward(PointReward $reward): RedirectResponse
    {
        $reward->update(['is_active' => ! $reward->is_active]);

        return back()->with('status', $reward->is_active ? 'Reward diaktifkan' : 'Reward dinonaktifkan');
    }

    public function destroyReward(PointReward $reward): RedirectResponse
    {
        // Already-redeemed vouchers keep their own copy of the terms, so deleting is safe.
        $reward->delete();

        return back()->with('status', 'Reward poin dihapus');
    }

    private function validateReward(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:500'],
            'type' => ['required', 'in:percentage,nominal'],
            'value' => ['required', 'numeric', 'min:1'],
            'max_discount' => ['nullable', 'numeric', 'min:0'],
            'min_order' => ['nullable', 'numeric', 'min:0'],
            'points_cost' => ['required', 'integer', 'min:1'],
            'min_tier' => ['required', 'in:'.implode(',', PointReward::TIERS)],
            'quota' => ['nullable', 'integer', 'min:1'],
        ]);
        $this->validateDiscountValue($data['type'], (float) $data['value']);

        $data['min_order'] = $data['min_order'] ?? 0;
        if ($data['type'] === 'nominal') {
            $data['max_discount'] = null;
        }

        return $data;
    }

    public function storeProduct(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'menu_id' => ['required', 'integer', 'exists:menus,id'],
            'type' => ['required', 'in:percentage,nominal'],
            'value' => ['required', 'numeric', 'min:0'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);
        $this->validateDiscountValue($data['type'], (float) $data['value']);
        ProductDiscount::create($data + ['is_active' => true]);
        return redirect()->route('admin.discounts.index', ['tab' => 'product'])->with('status', 'Diskon produk dibuat');
    }

    public function destroyProduct(ProductDiscount $discount): RedirectResponse
    {
        $discount->delete();
        return back()->with('status', 'Diskon produk dihapus');
    }

    public function storePromo(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:promo_codes,code'],
            'type' => ['required', 'in:percentage,nominal'],
            'value' => ['required', 'numeric', 'min:0'],
            'max_usage' => ['required', 'integer', 'min:1'],
            'min_order' => ['nullable', 'numeric', 'min:0'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);
        $this->validateDiscountValue($data['type'], (float) $data['value']);
        PromoCode::create($data + ['min_order' => $data['min_order'] ?? 0, 'is_active' => true]);
        return redirect()->route('admin.discounts.index', ['tab' => 'promo'])->with('status', 'Kode promo dibuat');
    }

    public function destroyPromo(PromoCode $promo): RedirectResponse
    {
        $promo->delete();
        return back()->with('status', 'Kode promo dihapus');
    }

    public function storeEvent(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', 'in:percentage,nominal'],
            'value' => ['required', 'numeric', 'min:0'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'menu_ids' => ['required', 'array', 'min:1'],
            'menu_ids.*' => ['integer', 'exists:menus,id'],
        ]);

        $this->validateDiscountValue($data['type'], (float) $data['value']);

        DB::transaction(function () use ($data) {
            $event = EventDiscount::create([
                'name' => $data['name'], 'type' => $data['type'], 'value' => $data['value'],
                'start_date' => $data['start_date'], 'end_date' => $data['end_date'], 'is_active' => true,
            ]);
            $event->menus()->sync($data['menu_ids']);
        });

        return redirect()->route('admin.discounts.index', ['tab' => 'event'])->with('status', 'Event diskon dibuat');
    }

    public function destroyEvent(EventDiscount $event): RedirectResponse
    {
        $event->delete();
        return back()->with('status', 'Event diskon dihapus');
    }
}
