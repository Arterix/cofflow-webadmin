@extends('admin.layouts.app')

@section('title', 'Pegawai · Cofflow Admin')
@section('page_title', 'Manajemen Pegawai')
@section('page_sub', 'Akun kasir dan admin')

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
    <div class="lg:col-span-2 bg-white rounded-2xl border border-primary-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
        <table class="w-full text-sm min-w-[720px]">
            <thead class="bg-primary-50 text-xs uppercase text-primary">
                <tr>
                    <th class="text-left px-4 py-3">Nama</th>
                    <th class="text-left px-4 py-3">Email</th>
                    <th class="text-left px-4 py-3">Telepon</th>
                    <th class="text-center px-4 py-3">Role</th>
                    <th class="text-center px-4 py-3">Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-primary-100">
                @foreach ($staff as $s)
                    <tr>
                        <td class="px-4 py-3 font-medium text-primary">{{ $s->name }}</td>
                        <td class="px-4 py-3 text-gray-600 text-xs">{{ $s->email }}</td>
                        <td class="px-4 py-3 text-gray-600 text-xs">{{ $s->phone ?? '-' }}</td>
                        <td class="px-4 py-3 text-center">
                            <span class="status-pill {{ $s->role === 'admin' ? 'is-strong' : 'is-success' }}">{{ $s->role }}</span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            @if ($s->is_active)
                                <span class="status-pill is-success">Aktif</span>
                            @else
                                <span class="status-pill is-muted">Nonaktif</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            <details class="js-popover inline-block text-left relative">
                                <summary class="btn-action btn-edit cursor-pointer list-none">Edit</summary>
                                <form method="POST" action="{{ route('admin.staff.update', $s) }}" class="absolute right-0 mt-1 z-10 bg-white border border-primary-100 rounded-lg p-3 w-64 space-y-2 shadow-lg">
                                    @csrf @method('PUT')
                                    <input name="name" value="{{ $s->name }}" required class="w-full rounded-md border border-primary-100 px-2 py-1 text-sm" />
                                    <input name="phone" value="{{ $s->phone }}" class="w-full rounded-md border border-primary-100 px-2 py-1 text-sm" />
                                    <select name="role" class="w-full rounded-md border border-primary-100 px-2 py-1 text-sm">
                                        <option value="kasir" @selected($s->role==='kasir')>kasir</option>
                                        <option value="admin" @selected($s->role==='admin')>admin</option>
                                    </select>
                                    <button class="btn-action btn-edit btn-action-block">Simpan</button>
                                </form>
                            </details>

                            @if ($s->id !== auth()->id())
                                <form method="POST" action="{{ route('admin.staff.toggle', $s) }}" class="inline">
                                    @csrf @method('PATCH')
                                    <button class="btn-action {{ $s->is_active ? 'btn-delete' : 'btn-add' }}">
                                        {{ $s->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                    </button>
                                </form>
                            @else
                                <button disabled class="px-2.5 py-1.5 rounded-md text-xs bg-gray-100 text-gray-400 cursor-not-allowed">Diri sendiri</button>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-primary-100 p-5 shadow-sm h-fit">
        <h2 class="font-display font-semibold text-primary mb-3">Tambah Pegawai</h2>
        <form method="POST" action="{{ route('admin.staff.store') }}" class="space-y-3">
            @csrf
            <input name="name" placeholder="Nama" required class="w-full rounded-lg border border-primary-100 px-3 py-2 text-sm" />
            <input name="email" type="email" placeholder="Email" required class="w-full rounded-lg border border-primary-100 px-3 py-2 text-sm" />
            <input name="phone" placeholder="Telepon" class="w-full rounded-lg border border-primary-100 px-3 py-2 text-sm" />
            <input name="password" type="password" placeholder="Password awal (min 8)" required class="w-full rounded-lg border border-primary-100 px-3 py-2 text-sm" />
            <select name="role" required class="w-full rounded-lg border border-primary-100 px-3 py-2 text-sm">
                <option value="kasir">Kasir</option>
                <option value="admin">Admin</option>
            </select>
            <button class="btn-action btn-add btn-action-lg btn-action-block">Buat Akun</button>
        </form>
    </div>
</div>
@endsection
