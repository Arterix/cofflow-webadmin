<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\MenuCategory;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        $categories = MenuCategory::withCount(['menus as menus_count' => function ($q) {
            $q->where('is_active', true);
        }])->orderBy('name')->get();

        return $this->success($categories);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:menu_categories,name'],
        ], [
            'name.unique' => 'Nama kategori sudah digunakan',
        ]);

        $category = MenuCategory::create([
            'name' => $data['name'],
            'slug' => Str::slug($data['name']),
        ]);

        return $this->success($category, 'Kategori berhasil dibuat', 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $category = MenuCategory::findOrFail($id);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:menu_categories,name,'.$id],
        ], [
            'name.unique' => 'Nama kategori sudah digunakan',
        ]);

        $category->update([
            'name' => $data['name'],
            'slug' => Str::slug($data['name']),
        ]);

        return $this->success($category, 'Kategori diperbarui');
    }

    public function destroy(int $id): JsonResponse
    {
        $category = MenuCategory::findOrFail($id);

        if ($category->menus()->exists()) {
            return $this->error('Kategori tidak dapat dihapus karena masih memiliki menu', 422);
        }

        $category->delete();

        return $this->success(null, 'Kategori dihapus');
    }
}
