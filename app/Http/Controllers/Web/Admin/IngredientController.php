<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IngredientController extends Controller
{
    public function index(Request $request): View
    {
        $filter = $request->query('filter');
        $items = Ingredient::orderBy('name')->get()->map(function ($i) {
            $i->is_critical = (float) $i->current_stock < (float) $i->minimum_stock;
            return $i;
        });

        if ($filter === 'critical') {
            $items = $items->where('is_critical', true)->values();
        } elseif ($filter === 'safe') {
            $items = $items->where('is_critical', false)->values();
        }

        return view('admin.ingredients.index', compact('items', 'filter'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'unit' => ['required', 'in:gram,ml,pcs,liter,kg'],
            'current_stock' => ['required', 'numeric', 'min:0'],
            'minimum_stock' => ['required', 'numeric', 'min:0'],
        ]);
        Ingredient::create($data);
        return back()->with('status', 'Bahan baku ditambahkan');
    }

    public function update(Request $request, Ingredient $ingredient): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'unit' => ['required', 'in:gram,ml,pcs,liter,kg'],
            'minimum_stock' => ['required', 'numeric', 'min:0'],
        ]);
        $ingredient->update($data);
        return back()->with('status', 'Bahan diperbarui');
    }

    public function restock(Request $request, Ingredient $ingredient): RedirectResponse
    {
        $data = $request->validate(['amount' => ['required', 'numeric', 'gt:0']]);
        $ingredient->current_stock = (float) $ingredient->current_stock + (float) $data['amount'];
        $ingredient->save();
        return back()->with('status', 'Stok bertambah');
    }

    public function destroy(Ingredient $ingredient): RedirectResponse
    {
        if ($ingredient->bomItems()->exists()) {
            return back()->withErrors(['name' => 'Bahan masih digunakan di BOM']);
        }
        $ingredient->delete();
        return back()->with('status', 'Bahan dihapus');
    }
}
