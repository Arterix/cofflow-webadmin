<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use App\Models\StockAdjustment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            'name'          => ['required', 'string', 'max:150'],
            'unit'          => ['required', 'in:gram,ml,pcs,liter,kg'],
            'current_stock' => ['required', 'numeric', 'min:0'],
            'minimum_stock' => ['required', 'numeric', 'min:0'],
        ]);
        Ingredient::create($data);
        return back()->with('status', 'Bahan baku ditambahkan');
    }

    /**
     * Edit info bahan (nama, unit, minimum stok). Tidak mengubah stok saat ini.
     */
    public function update(Request $request, Ingredient $ingredient): RedirectResponse
    {
        $data = $request->validate([
            'name'          => ['required', 'string', 'max:150'],
            'unit'          => ['required', 'in:gram,ml,pcs,liter,kg'],
            'minimum_stock' => ['required', 'numeric', 'min:0'],
        ]);
        $ingredient->update($data);
        return back()->with('status', 'Bahan diperbarui');
    }

    /**
     * Restock: tambahkan jumlah ke stok saat ini. Catat ke stock_adjustments.
     */
    public function restock(Request $request, Ingredient $ingredient): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'notes'  => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($ingredient, $data) {
            $before = (float) $ingredient->current_stock;
            $after  = $before + (float) $data['amount'];

            $ingredient->current_stock = $after;
            $ingredient->save();

            StockAdjustment::create([
                'ingredient_id' => $ingredient->id,
                'adjusted_by'   => auth()->id(),
                'type'          => 'restock',
                'before'        => $before,
                'after'         => $after,
                'delta'         => $after - $before,
                'reason'        => 'restock',
                'notes'         => $data['notes'] ?? null,
            ]);
        });

        return back()->with('status', 'Stok bertambah ' . $data['amount'] . ' ' . $ingredient->unit);
    }

    /**
     * Adjust: set stok ke nilai absolut (koreksi manual). Wajib menyertakan alasan.
     * Catat ke stock_adjustments untuk audit trail.
     */
    public function adjust(Request $request, Ingredient $ingredient): RedirectResponse
    {
        $data = $request->validate([
            'new_stock' => ['required', 'numeric', 'min:0'],
            'reason'    => ['required', 'in:correction,opname_manual,damage,initial_count,other'],
            'notes'     => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($ingredient, $data) {
            $before = (float) $ingredient->current_stock;
            $after  = (float) $data['new_stock'];

            $ingredient->current_stock = $after;
            $ingredient->save();

            StockAdjustment::create([
                'ingredient_id' => $ingredient->id,
                'adjusted_by'   => auth()->id(),
                'type'          => 'manual',
                'before'        => $before,
                'after'         => $after,
                'delta'         => $after - $before,
                'reason'        => $data['reason'],
                'notes'         => $data['notes'] ?? null,
            ]);
        });

        return back()->with('status', "Stok {$ingredient->name} diatur ke {$data['new_stock']} {$ingredient->unit}");
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
