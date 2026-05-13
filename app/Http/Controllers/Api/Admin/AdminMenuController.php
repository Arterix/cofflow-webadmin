<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\BomItem;
use App\Models\Ingredient;
use App\Models\Menu;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AdminMenuController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        $menus = Menu::with('category')
            ->withCount('bomItems')
            ->orderBy('name')
            ->get();

        return $this->success($menus);
    }

    public function show(int $id): JsonResponse
    {
        $menu = Menu::with(['category', 'condimentGroups.options', 'bomItems.ingredient'])
            ->findOrFail($id);

        return $this->success($menu);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'menu_category_id' => ['required', 'integer', 'exists:menu_categories,id'],
            'is_active' => ['nullable', 'boolean'],
            'image' => ['nullable', 'image', 'max:4096'],
        ]);

        $imageUrl = null;
        if ($request->hasFile('image')) {
            $imageUrl = $this->uploadImage($request->file('image'));
        }

        $menu = Menu::create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'price' => $data['price'],
            'menu_category_id' => $data['menu_category_id'],
            'is_active' => $data['is_active'] ?? true,
            'image_url' => $imageUrl,
        ]);

        return $this->success($menu->load('category'), 'Menu berhasil dibuat', 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $menu = Menu::findOrFail($id);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'menu_category_id' => ['sometimes', 'integer', 'exists:menu_categories,id'],
            'is_active' => ['sometimes', 'boolean'],
            'image' => ['nullable', 'image', 'max:4096'],
        ]);

        if ($request->hasFile('image')) {
            $data['image_url'] = $this->uploadImage($request->file('image'));
        }

        unset($data['image']);
        $menu->update($data);

        return $this->success($menu->fresh('category'), 'Menu diperbarui');
    }

    public function toggle(int $id): JsonResponse
    {
        $menu = Menu::findOrFail($id);
        $menu->is_active = ! $menu->is_active;
        $menu->save();

        return $this->success($menu, 'Status menu diubah');
    }

    public function destroy(int $id): JsonResponse
    {
        $menu = Menu::findOrFail($id);

        $hasActiveOrder = $menu->orderItems()
            ->whereHas('order', function ($q) {
                $q->whereNotIn('status', ['completed', 'cancelled']);
            })->exists();

        if ($hasActiveOrder) {
            return $this->error('Menu tidak dapat dihapus karena masih ada pesanan aktif', 422);
        }

        $menu->delete();

        return $this->success(null, 'Menu dihapus');
    }

    public function getBom(int $id): JsonResponse
    {
        $menu = Menu::with('bomItems.ingredient')->findOrFail($id);

        return $this->success($menu->bomItems);
    }

    public function updateBom(Request $request, int $id): JsonResponse
    {
        $menu = Menu::findOrFail($id);

        $data = $request->validate([
            'items' => ['required', 'array'],
            'items.*.ingredient_id' => ['required', 'integer', 'exists:ingredients,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
        ], [
            'items.*.quantity.gt' => 'Jumlah bahan harus lebih dari 0',
        ]);

        DB::transaction(function () use ($menu, $data) {
            $menu->bomItems()->delete();
            foreach ($data['items'] as $item) {
                BomItem::create([
                    'menu_id' => $menu->id,
                    'ingredient_id' => $item['ingredient_id'],
                    'quantity' => $item['quantity'],
                ]);
            }
        });

        return $this->success(
            $menu->load('bomItems.ingredient')->bomItems,
            'BOM diperbarui'
        );
    }

    public function attachCondimentGroup(int $menuId, int $groupId): JsonResponse
    {
        $menu = Menu::findOrFail($menuId);
        $menu->condimentGroups()->syncWithoutDetaching([$groupId]);

        return $this->success(null, 'Condiment group ditambahkan ke menu');
    }

    public function detachCondimentGroup(int $menuId, int $groupId): JsonResponse
    {
        $menu = Menu::findOrFail($menuId);
        $menu->condimentGroups()->detach($groupId);

        return $this->success(null, 'Condiment group dilepas dari menu');
    }

    private function uploadImage($file): string
    {
        // TODO: Replace with Supabase Storage upload when SUPABASE_KEY is configured.
        // For now we store locally under the public disk.
        $path = $file->store('menus', 'public');

        return Storage::disk('public')->url($path);
    }
}
