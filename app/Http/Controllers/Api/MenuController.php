<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class MenuController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        $menus = Menu::with(['category', 'condimentGroups.options'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return $this->success($menus);
    }

    public function show(int $id): JsonResponse
    {
        $menu = Menu::with(['category', 'condimentGroups.options'])
            ->where('is_active', true)
            ->findOrFail($id);

        return $this->success($menu);
    }

    public function categories(): JsonResponse
    {
        $categories = MenuCategory::orderBy('name')->get();

        return $this->success($categories);
    }
}
