<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\CondimentGroup;
use App\Models\CondimentOption;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CondimentController extends Controller
{
    use ApiResponse;

    public function indexGroups(): JsonResponse
    {
        $groups = CondimentGroup::with('options')->orderBy('name')->get();

        return $this->success($groups);
    }

    public function storeGroup(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', 'in:single_select,multi_select'],
            'is_required' => ['nullable', 'boolean'],
        ]);

        $group = CondimentGroup::create([
            'name' => $data['name'],
            'type' => $data['type'],
            'is_required' => $data['is_required'] ?? false,
        ]);

        return $this->success($group, 'Condiment group dibuat', 201);
    }

    public function updateGroup(Request $request, int $id): JsonResponse
    {
        $group = CondimentGroup::findOrFail($id);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'type' => ['sometimes', 'in:single_select,multi_select'],
            'is_required' => ['sometimes', 'boolean'],
        ]);

        $group->update($data);

        return $this->success($group, 'Condiment group diperbarui');
    }

    public function destroyGroup(int $id): JsonResponse
    {
        $group = CondimentGroup::findOrFail($id);
        $group->delete();

        return $this->success(null, 'Condiment group dihapus');
    }

    public function storeOption(Request $request, int $groupId): JsonResponse
    {
        $group = CondimentGroup::findOrFail($groupId);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'additional_price' => ['nullable', 'numeric', 'min:0'],
        ]);

        $option = CondimentOption::create([
            'condiment_group_id' => $group->id,
            'name' => $data['name'],
            'additional_price' => $data['additional_price'] ?? 0,
        ]);

        return $this->success($option, 'Option dibuat', 201);
    }

    public function updateOption(Request $request, int $id): JsonResponse
    {
        $option = CondimentOption::findOrFail($id);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'additional_price' => ['sometimes', 'numeric', 'min:0'],
        ]);

        $option->update($data);

        return $this->success($option, 'Option diperbarui');
    }

    public function destroyOption(int $id): JsonResponse
    {
        $option = CondimentOption::findOrFail($id);
        $option->delete();

        return $this->success(null, 'Option dihapus');
    }
}
