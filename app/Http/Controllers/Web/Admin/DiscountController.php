<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\EventDiscount;
use App\Models\Menu;
use App\Models\ProductDiscount;
use App\Models\PromoCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DiscountController extends Controller
{
    public function index(Request $request): View
    {
        $tab = $request->query('tab', 'product');
        $menus = Menu::orderBy('name')->get();

        $products = ProductDiscount::with('menu')->orderByDesc('id')->get();
        $promos = PromoCode::orderByDesc('id')->get();
        $events = EventDiscount::with('menus')->orderByDesc('id')->get();

        return view('admin.discounts.index', compact('tab', 'menus', 'products', 'promos', 'events'));
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
