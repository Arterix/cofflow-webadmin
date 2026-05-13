<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IngredientController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        $ingredients = Ingredient::orderBy('name')->get()->map(function ($i) {
            $i->is_critical = (float) $i->current_stock < (float) $i->minimum_stock;
            return $i;
        });

        return $this->success($ingredients);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'unit' => ['required', 'in:gram,ml,pcs,liter,kg'],
            'current_stock' => ['required', 'numeric', 'min:0'],
            'minimum_stock' => ['required', 'numeric', 'min:0'],
        ]);

        $ingredient = Ingredient::create($data);

        return $this->success($ingredient, 'Bahan baku dibuat', 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $ingredient = Ingredient::findOrFail($id);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:150'],
            'unit' => ['sometimes', 'in:gram,ml,pcs,liter,kg'],
            'minimum_stock' => ['sometimes', 'numeric', 'min:0'],
        ]);

        $ingredient->update($data);

        return $this->success($ingredient, 'Bahan baku diperbarui');
    }

    public function restock(Request $request, int $id): JsonResponse
    {
        $ingredient = Ingredient::findOrFail($id);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
        ]);

        $ingredient->current_stock = (float) $ingredient->current_stock + (float) $data['amount'];
        $ingredient->save();

        return $this->success($ingredient, 'Stok bertambah');
    }

    public function destroy(int $id): JsonResponse
    {
        $ingredient = Ingredient::findOrFail($id);

        if ($ingredient->bomItems()->exists()) {
            return $this->error('Bahan baku tidak dapat dihapus karena masih digunakan di BOM', 422);
        }

        $ingredient->delete();

        return $this->success(null, 'Bahan baku dihapus');
    }
}
