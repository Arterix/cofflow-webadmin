@extends('admin.layouts.app')

@section('title', 'Menu · Cofflow Admin')
@section('page_title', 'Manajemen Menu')
@section('page_sub', 'Kelola menu coffee shop')

@section('content')
<div class="space-y-4">
    <div class="flex flex-wrap gap-3 justify-between items-center">
        <form method="GET" class="flex flex-wrap gap-2">
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Cari menu..."
                   class="rounded-lg border border-primary-100 bg-white px-3 py-2 text-sm w-56" />
            <select name="category" class="rounded-lg border border-primary-100 bg-white px-3 py-2 text-sm">
                <option value="">Semua kategori</option>
                @foreach ($categories as $c)
                    <option value="{{ $c->id }}" @selected(request('category') == $c->id)>{{ $c->name }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn-action btn-edit btn-action-lg">Filter</button>
        </form>

        <a href="{{ route('admin.menus.create') }}" class="btn-action btn-add btn-action-lg">
            + Tambah Menu
        </a>
    </div>

    <div class="bg-white rounded-2xl border border-primary-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm min-w-[820px]">
                <thead class="bg-primary-50 text-primary text-xs uppercase tracking-wide">
                    <tr>
                        <th class="text-left px-4 py-3">Foto</th>
                        <th class="text-left px-4 py-3">Nama</th>
                        <th class="text-left px-4 py-3">Kategori</th>
                        <th class="text-right px-4 py-3">Harga</th>
                        <th class="text-center px-4 py-3">BOM</th>
                        <th class="text-center px-4 py-3">Status</th>
                        <th class="text-right px-4 py-3">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-primary-100">
                    @forelse ($menus as $menu)
                        <tr class="hover:bg-secondary/40">
                            <td class="px-4 py-3">
                                @if ($menu->image_url)
                                    <img src="{{ $menu->image_url }}" class="h-12 w-12 rounded-lg object-cover" alt="" />
                                @else
                                    <div class="h-12 w-12 rounded-lg bg-secondary-dark flex items-center justify-center text-xs text-primary/60">No img</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 font-medium text-primary">{{ $menu->name }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $menu->category?->name }}</td>
                            <td class="px-4 py-3 text-right">Rp {{ number_format((float) $menu->price, 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-center">{{ $menu->bom_items_count }}</td>
                            <td class="px-4 py-3 text-center">
                                @if ($menu->is_active)
                                    <span class="status-pill is-success">Aktif</span>
                                @else
                                    <span class="status-pill is-muted">Nonaktif</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="inline-flex flex-wrap justify-end gap-1">
                                    <a href="{{ route('admin.menus.bom.edit', $menu) }}" class="btn-action btn-info">BOM</a>
                                    <a href="{{ route('admin.menus.edit', $menu) }}" class="btn-action btn-edit">Edit</a>
                                    <form method="POST" action="{{ route('admin.menus.toggle', $menu) }}" class="inline">
                                        @csrf @method('PATCH')
                                        <button class="px-2.5 py-1.5 rounded-md text-xs bg-secondary-dark text-primary hover:bg-primary hover:text-white">{{ $menu->is_active ? 'Off' : 'On' }}</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.menus.destroy', $menu) }}" class="inline" onsubmit="return confirm('Hapus menu ini?')">
                                        @csrf @method('DELETE')
                                        <button class="btn-action btn-delete">Hapus</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-12 text-center text-sm text-gray-500">
                                Belum ada menu. <a href="{{ route('admin.menus.create') }}" class="text-accent-dark font-medium">Tambah menu pertama</a>.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
