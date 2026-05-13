@extends('admin.layouts.app')

@section('title', 'Stok · Cofflow Admin')
@section('page_title', 'Manajemen Stok')
@section('page_sub', 'Bahan baku & restock')

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
    <div class="lg:col-span-2 space-y-3">
        <div class="flex gap-2">
            @foreach ([['','Semua'],['critical','Kritis'],['safe','Aman']] as [$k, $label])
                <a href="{{ route('admin.ingredients.index', $k ? ['filter' => $k] : []) }}"
                   class="px-3 py-1.5 text-xs rounded-full border {{ ($filter ?? '') === $k ? 'bg-primary text-white border-primary' : 'bg-white border-primary-100 text-primary' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>

        <div class="bg-white rounded-2xl border border-primary-100 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
            <table class="w-full text-sm min-w-[640px]">
                <thead class="bg-primary-50 text-xs uppercase tracking-wide text-primary">
                    <tr>
                        <th class="text-left px-4 py-3">Nama</th>
                        <th class="text-center px-4 py-3">Unit</th>
                        <th class="text-right px-4 py-3">Stok Saat Ini</th>
                        <th class="text-right px-4 py-3">Min</th>
                        <th class="text-right px-4 py-3">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-primary-100">
                    @forelse ($items as $i)
                        <tr class="{{ $i->is_critical ? 'bg-alert-50' : '' }}">
                            <td class="px-4 py-3">
                                <div class="font-medium text-primary">{{ $i->name }}</div>
                                @if ($i->is_critical)
                                    <span class="status-pill is-danger mt-0.5">Kritis</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center text-xs text-gray-500">{{ $i->unit }}</td>
                            <td class="px-4 py-3 text-right">{{ rtrim(rtrim((string) $i->current_stock, '0'), '.') }}</td>
                            <td class="px-4 py-3 text-right text-gray-500">{{ rtrim(rtrim((string) $i->minimum_stock, '0'), '.') }}</td>
                            <td class="px-4 py-3 text-right">
                                <details class="js-popover inline-block text-left relative">
                                    <summary class="btn-action btn-add cursor-pointer list-none">Restock</summary>
                                    <form method="POST" action="{{ route('admin.ingredients.restock', $i) }}" class="absolute right-0 mt-1 z-10 bg-white border border-primary-100 rounded-lg p-2 flex gap-1 shadow-lg">
                                        @csrf @method('PATCH')
                                        <input type="number" step="0.001" min="0.001" name="amount" placeholder="+jumlah" class="w-24 rounded-md border border-primary-100 px-2 py-1 text-xs" required />
                                        <button class="btn-action btn-edit">OK</button>
                                    </form>
                                </details>

                                <details class="js-popover inline-block text-left relative">
                                    <summary class="btn-action btn-edit cursor-pointer list-none">Edit</summary>
                                    <form method="POST" action="{{ route('admin.ingredients.update', $i) }}" class="absolute right-0 mt-1 z-10 bg-white border border-primary-100 rounded-lg p-3 w-64 space-y-2 shadow-lg">
                                        @csrf @method('PUT')
                                        <input name="name" value="{{ $i->name }}" required class="w-full rounded-md border border-primary-100 px-2 py-1 text-sm" />
                                        <select name="unit" class="w-full rounded-md border border-primary-100 px-2 py-1 text-sm">
                                            @foreach (['gram','ml','pcs','liter','kg'] as $u)
                                                <option value="{{ $u }}" @selected($u === $i->unit)>{{ $u }}</option>
                                            @endforeach
                                        </select>
                                        <input type="number" step="0.001" min="0" name="minimum_stock" value="{{ $i->minimum_stock }}" required class="w-full rounded-md border border-primary-100 px-2 py-1 text-sm" />
                                        <button class="btn-action btn-edit btn-action-block">Simpan</button>
                                    </form>
                                </details>

                                <form method="POST" action="{{ route('admin.ingredients.destroy', $i) }}" class="inline" onsubmit="return confirm('Hapus bahan?')">
                                    @csrf @method('DELETE')
                                    <button class="btn-action btn-delete">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-12 text-center text-sm text-gray-500">
                                <svg class="mx-auto h-10 w-10 text-gray-300 mb-2" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7l9-4 9 4-9 4-9-4zm0 6l9 4 9-4M3 17l9 4 9-4"/></svg>
                                Belum ada bahan baku. Tambah lewat form di samping.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-primary-100 p-5 shadow-sm h-fit">
        <h2 class="font-display font-semibold text-primary mb-3">Tambah Bahan</h2>
        <form method="POST" action="{{ route('admin.ingredients.store') }}" class="space-y-3">
            @csrf
            <input name="name" placeholder="Nama bahan" required class="w-full rounded-lg border border-primary-100 px-3 py-2 text-sm" />
            <select name="unit" required class="w-full rounded-lg border border-primary-100 px-3 py-2 text-sm">
                @foreach (['gram','ml','pcs','liter','kg'] as $u)
                    <option value="{{ $u }}">{{ $u }}</option>
                @endforeach
            </select>
            <input type="number" step="0.001" min="0" name="current_stock" placeholder="Stok awal" required class="w-full rounded-lg border border-primary-100 px-3 py-2 text-sm" />
            <input type="number" step="0.001" min="0" name="minimum_stock" placeholder="Minimum stok" required class="w-full rounded-lg border border-primary-100 px-3 py-2 text-sm" />
            <button class="btn-action btn-add btn-action-lg btn-action-block">Tambah</button>
        </form>
    </div>
</div>
@endsection
