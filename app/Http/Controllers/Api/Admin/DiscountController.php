<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\EventDiscount;
use App\Models\ProductDiscount;
use App\Models\PromoCode;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DiscountController extends Controller
{
    use ApiResponse;

    // ---- Product Discount ----
    public function indexProduct(): JsonResponse
    {
        $items = ProductDiscount::with('menu')->orderByDesc('id')->get();

        return $this->success($items);
    }

    public function storeProduct(Request $request): JsonResponse
    {
        $data = $request->validate([
            'menu_id' => ['required', 'integer', 'exists:menus,id'],
            'type' => ['required', 'in:percentage,nominal'],
            'value' => ['required', 'numeric', 'min:0'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $disc = ProductDiscount::create($data + ['is_active' => $data['is_active'] ?? true]);

        return $this->success($disc->load('menu'), 'Diskon produk dibuat', 201);
    }

    public function updateProduct(Request $request, int $id): JsonResponse
    {
        $disc = ProductDiscount::findOrFail($id);

        $data = $request->validate([
            'type' => ['sometimes', 'in:percentage,nominal'],
            'value' => ['sometimes', 'numeric', 'min:0'],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['sometimes', 'date'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $disc->update($data);

        return $this->success($disc, 'Diskon produk diperbarui');
    }

    public function destroyProduct(int $id): JsonResponse
    {
        ProductDiscount::findOrFail($id)->delete();

        return $this->success(null, 'Diskon produk dihapus');
    }

    // ---- Promo Code ----
    public function indexPromo(): JsonResponse
    {
        $items = PromoCode::orderByDesc('id')->get();

        return $this->success($items);
    }

    public function storePromo(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:promo_codes,code'],
            'type' => ['required', 'in:percentage,nominal'],
            'value' => ['required', 'numeric', 'min:0'],
            'max_usage' => ['nullable', 'integer', 'min:1'],
            'min_order' => ['nullable', 'numeric', 'min:0'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $promo = PromoCode::create(array_merge($data, [
            'max_usage' => $data['max_usage'] ?? 1,
            'min_order' => $data['min_order'] ?? 0,
            'is_active' => $data['is_active'] ?? true,
        ]));

        return $this->success($promo, 'Kode promo dibuat', 201);
    }

    public function updatePromo(Request $request, int $id): JsonResponse
    {
        $promo = PromoCode::findOrFail($id);

        $data = $request->validate([
            'type' => ['sometimes', 'in:percentage,nominal'],
            'value' => ['sometimes', 'numeric', 'min:0'],
            'max_usage' => ['sometimes', 'integer', 'min:1'],
            'min_order' => ['sometimes', 'numeric', 'min:0'],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['sometimes', 'date'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $promo->update($data);

        return $this->success($promo, 'Kode promo diperbarui');
    }

    public function destroyPromo(int $id): JsonResponse
    {
        PromoCode::findOrFail($id)->delete();

        return $this->success(null, 'Kode promo dihapus');
    }

    // ---- Event Discount ----
    public function indexEvent(): JsonResponse
    {
        $items = EventDiscount::with('menus')->orderByDesc('id')->get();

        return $this->success($items);
    }

    public function storeEvent(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', 'in:percentage,nominal'],
            'value' => ['required', 'numeric', 'min:0'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'is_active' => ['nullable', 'boolean'],
            'menu_ids' => ['required', 'array', 'min:1'],
            'menu_ids.*' => ['integer', 'exists:menus,id'],
        ]);

        $event = DB::transaction(function () use ($data) {
            $event = EventDiscount::create([
                'name' => $data['name'],
                'type' => $data['type'],
                'value' => $data['value'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'is_active' => $data['is_active'] ?? true,
            ]);
            $event->menus()->sync($data['menu_ids']);
            return $event;
        });

        return $this->success($event->load('menus'), 'Event diskon dibuat', 201);
    }

    public function updateEvent(Request $request, int $id): JsonResponse
    {
        $event = EventDiscount::findOrFail($id);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:150'],
            'type' => ['sometimes', 'in:percentage,nominal'],
            'value' => ['sometimes', 'numeric', 'min:0'],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['sometimes', 'date'],
            'is_active' => ['sometimes', 'boolean'],
            'menu_ids' => ['sometimes', 'array'],
            'menu_ids.*' => ['integer', 'exists:menus,id'],
        ]);

        DB::transaction(function () use ($event, $data) {
            $event->update(collect($data)->except('menu_ids')->toArray());
            if (array_key_exists('menu_ids', $data)) {
                $event->menus()->sync($data['menu_ids']);
            }
        });

        return $this->success($event->fresh('menus'), 'Event diskon diperbarui');
    }

    public function destroyEvent(int $id): JsonResponse
    {
        EventDiscount::findOrFail($id)->delete();

        return $this->success(null, 'Event diskon dihapus');
    }
}
