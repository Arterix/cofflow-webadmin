<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffManagementController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        $staff = User::whereIn('role', ['kasir', 'admin'])->orderBy('name')->get();

        return $this->success($staff);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'in:kasir,admin'],
        ]);

        $user = User::create($data + ['is_active' => true]);

        return $this->success($user, 'Pegawai dibuat', 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:20'],
            'role' => ['sometimes', 'in:kasir,admin'],
        ]);

        $roleChanged = isset($data['role']) && $data['role'] !== $user->role;
        $user->update($data);

        if ($roleChanged) {
            $user->tokens()->delete();
        }

        return $this->success($user->fresh(), 'Pegawai diperbarui');
    }

    public function toggle(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        if ($user->id === $request->user()->id) {
            return $this->error('Tidak dapat menonaktifkan akun sendiri', 422);
        }

        $user->is_active = ! $user->is_active;
        $user->save();

        if (! $user->is_active) {
            $user->tokens()->delete();
        }

        return $this->success($user, 'Status pegawai diubah');
    }
}
