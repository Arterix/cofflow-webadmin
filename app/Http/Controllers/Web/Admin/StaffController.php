<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StaffController extends Controller
{
    public function index(): View
    {
        $staff = User::whereIn('role', ['kasir', 'admin'])->orderBy('name')->get();
        return view('admin.staff.index', compact('staff'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'in:kasir,admin'],
        ]);
        User::create($data + ['is_active' => true]);
        return back()->with('status', 'Pegawai dibuat');
    }

    public function update(Request $request, User $staff): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:20'],
            'role' => ['required', 'in:kasir,admin'],
        ]);
        $roleChanged = $data['role'] !== $staff->role;
        $staff->update($data);
        if ($roleChanged) {
            $staff->tokens()->delete();
        }
        return back()->with('status', 'Pegawai diperbarui');
    }

    public function toggle(Request $request, User $staff): RedirectResponse
    {
        if ($staff->id === $request->user()->id) {
            return back()->withErrors(['name' => 'Tidak dapat menonaktifkan akun sendiri']);
        }
        $staff->is_active = ! $staff->is_active;
        $staff->save();
        if (! $staff->is_active) {
            $staff->tokens()->delete();
        }
        return back()->with('status', 'Status pegawai diubah');
    }
}
