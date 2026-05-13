<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\CondimentGroup;
use App\Models\CondimentOption;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CondimentController extends Controller
{
    public function index(): View
    {
        $groups = CondimentGroup::with('options')->orderBy('name')->get();
        return view('admin.condiments.index', compact('groups'));
    }

    public function storeGroup(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', 'in:single_select,multi_select'],
            'is_required' => ['nullable', 'boolean'],
        ]);
        CondimentGroup::create($data + ['is_required' => $request->boolean('is_required')]);
        return back()->with('status', 'Group dibuat');
    }

    public function destroyGroup(CondimentGroup $group): RedirectResponse
    {
        $group->delete();
        return back()->with('status', 'Group dihapus');
    }

    public function storeOption(Request $request, CondimentGroup $group): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'additional_price' => ['nullable', 'numeric', 'min:0'],
        ]);
        $nextOrder = (int) CondimentOption::where('condiment_group_id', $group->id)->max('sort_order') + 1;

        CondimentOption::create([
            'condiment_group_id' => $group->id,
            'name' => $data['name'],
            'additional_price' => $data['additional_price'] ?? 0,
            'sort_order' => $nextOrder,
        ]);
        return back()->with('status', 'Option dibuat');
    }

    public function reorderOptions(Request $request, CondimentGroup $group): JsonResponse
    {
        $data = $request->validate([
            'option_ids' => ['required', 'array', 'min:1'],
            'option_ids.*' => ['integer', 'exists:condiment_options,id'],
        ]);

        DB::transaction(function () use ($group, $data) {
            foreach ($data['option_ids'] as $index => $id) {
                CondimentOption::where('id', $id)
                    ->where('condiment_group_id', $group->id)
                    ->update(['sort_order' => $index]);
            }
        });

        return response()->json(['success' => true]);
    }

    public function updateOption(Request $request, CondimentOption $option): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'additional_price' => ['nullable', 'numeric', 'min:0'],
        ]);
        $option->update([
            'name' => $data['name'],
            'additional_price' => $data['additional_price'] ?? 0,
        ]);
        return back()->with('status', 'Option diperbarui');
    }

    public function destroyOption(CondimentOption $option): RedirectResponse
    {
        $option->delete();
        return back()->with('status', 'Option dihapus');
    }
}
