<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\BomItem;
use App\Models\CondimentGroup;
use App\Models\Ingredient;
use App\Models\Menu;
use App\Models\MenuCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class MenuController extends Controller
{
    public function index(Request $request): View
    {
        $menus = Menu::with('category')
            ->withCount('bomItems')
            ->when($request->query('q'), fn ($q, $term) => $q->where('name', 'like', "%{$term}%"))
            ->when($request->query('category'), fn ($q, $cid) => $q->where('menu_category_id', $cid))
            ->orderBy('name')
            ->get();

        $categories = MenuCategory::orderBy('name')->get();

        return view('admin.menus.index', compact('menus', 'categories'));
    }

    public function create(): View
    {
        $categories = MenuCategory::orderBy('name')->get();
        return view('admin.menus.form', ['menu' => new Menu(), 'categories' => $categories]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateMenu($request);
        $data['image_url'] = $this->uploadImage($request);
        $data['is_active'] = $request->boolean('is_active', true);

        Menu::create($data);

        return redirect()->route('admin.menus.index')->with('status', 'Menu berhasil dibuat');
    }

    public function edit(Menu $menu): View
    {
        $categories = MenuCategory::orderBy('name')->get();
        return view('admin.menus.form', compact('menu', 'categories'));
    }

    public function update(Request $request, Menu $menu): RedirectResponse
    {
        $data = $this->validateMenu($request);
        $data['is_active'] = $request->boolean('is_active', true);

        $newImage = $this->uploadImage($request);
        if ($newImage) {
            $data['image_url'] = $newImage;
        }

        $menu->update($data);

        return redirect()->route('admin.menus.index')->with('status', 'Menu diperbarui');
    }

    public function toggle(Menu $menu): RedirectResponse
    {
        $menu->is_active = ! $menu->is_active;
        $menu->save();

        return back()->with('status', 'Status menu diubah');
    }

    public function destroy(Menu $menu): RedirectResponse
    {
        $hasActive = $menu->orderItems()
            ->whereHas('order', fn ($q) => $q->whereNotIn('status', ['completed', 'cancelled']))
            ->exists();

        if ($hasActive) {
            return back()->withErrors(['menu' => 'Menu tidak dapat dihapus karena masih ada pesanan aktif']);
        }

        $menu->delete();

        return redirect()->route('admin.menus.index')->with('status', 'Menu dihapus');
    }

    public function bomEdit(Menu $menu): View
    {
        $menu->load('bomItems.ingredient', 'condimentGroups');
        $ingredients = Ingredient::orderBy('name')->get();
        $allGroups = CondimentGroup::orderBy('name')->get();

        return view('admin.menus.bom', compact('menu', 'ingredients', 'allGroups'));
    }

    public function bomUpdate(Request $request, Menu $menu): RedirectResponse
    {
        $data = $request->validate([
            'items' => ['nullable', 'array'],
            'items.*.ingredient_id' => ['required', 'integer', 'exists:ingredients,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'condiment_group_ids' => ['nullable', 'array'],
            'condiment_group_ids.*' => ['integer', 'exists:condiment_groups,id'],
        ]);

        DB::transaction(function () use ($menu, $data) {
            $menu->bomItems()->delete();
            foreach ($data['items'] ?? [] as $item) {
                BomItem::create([
                    'menu_id' => $menu->id,
                    'ingredient_id' => $item['ingredient_id'],
                    'quantity' => $item['quantity'],
                ]);
            }
            $menu->condimentGroups()->sync($data['condiment_group_ids'] ?? []);
        });

        return back()->with('status', 'BOM dan condiment menu diperbarui');
    }

    private function validateMenu(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'menu_category_id' => ['required', 'integer', 'exists:menu_categories,id'],
            'image' => ['nullable', 'image', 'max:4096'],
        ]);
    }

    private function uploadImage(Request $request): ?string
    {
        if (! $request->hasFile('image')) {
            return null;
        }
        $path = $request->file('image')->store('menus', 'public');

        return Storage::disk('public')->url($path);
    }
}
