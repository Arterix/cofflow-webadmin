<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\MenuCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(): View
    {
        $categories = MenuCategory::withCount('menus')->orderBy('name')->get();
        return view('admin.categories.index', compact('categories'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:menu_categories,name'],
        ]);
        MenuCategory::create([
            'name' => $data['name'],
            'slug' => Str::slug($data['name']),
        ]);

        return back()->with('status', 'Kategori dibuat');
    }

    public function update(Request $request, MenuCategory $category): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:menu_categories,name,'.$category->id],
        ]);
        $category->update(['name' => $data['name'], 'slug' => Str::slug($data['name'])]);

        return back()->with('status', 'Kategori diperbarui');
    }

    public function destroy(MenuCategory $category): RedirectResponse
    {
        if ($category->menus()->exists()) {
            return back()->withErrors(['name' => 'Kategori tidak dapat dihapus karena masih memiliki menu']);
        }
        $category->delete();
        return back()->with('status', 'Kategori dihapus');
    }
}
