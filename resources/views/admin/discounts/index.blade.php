@extends('admin.layouts.app')

@section('title', 'Diskon · Cofflow Admin')
@section('page_title', 'Manajemen Diskon')
@section('page_sub', 'Diskon produk, kode promo, dan event')

@php
    $today = \Carbon\Carbon::today();
    $statusOf = function ($d) use ($today) {
        $start = \Carbon\Carbon::parse($d->start_date);
        $end = \Carbon\Carbon::parse($d->end_date);
        if ($today->lt($start)) return ['Belum mulai', 'is-warning'];
        if ($today->gt($end)) return ['Kedaluwarsa', 'is-danger'];
        if (! $d->is_active) return ['Nonaktif', 'is-muted'];
        return ['Aktif', 'is-success'];
    };
@endphp

@section('content')
<div class="space-y-5">
    <div class="flex gap-2 border-b border-primary-100 overflow-x-auto -mx-1 px-1">
        @foreach (['product' => 'Diskon Produk', 'promo' => 'Kode Promo', 'event' => 'Event Diskon'] as $k => $label)
            <a href="{{ route('admin.discounts.index', ['tab' => $k]) }}"
               class="px-4 py-2 text-sm font-medium border-b-2 -mb-px whitespace-nowrap {{ $tab === $k ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:text-primary' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    {{-- ============ PRODUCT ============ --}}
    @if ($tab === 'product')
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
            <div class="lg:col-span-2 bg-white rounded-2xl border border-primary-100 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                <table class="w-full text-sm min-w-[720px]">
                    <thead class="bg-primary-50 text-xs uppercase text-primary">
                        <tr>
                            <th class="text-left px-4 py-3">Menu</th>
                            <th class="text-left px-4 py-3">Tipe</th>
                            <th class="text-right px-4 py-3">Nilai</th>
                            <th class="text-left px-4 py-3">Periode</th>
                            <th class="text-center px-4 py-3">Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-primary-100">
                        @forelse ($products as $p)
                            @php [$lab, $cls] = $statusOf($p); @endphp
                            <tr>
                                <td class="px-4 py-3 font-medium text-primary">{{ $p->menu?->name }}</td>
                                <td class="px-4 py-3">{{ $p->type }}</td>
                                <td class="px-4 py-3 text-right">{{ $p->type === 'percentage' ? rtrim(rtrim($p->value, '0'), '.').'%' : 'Rp '.number_format((float) $p->value, 0, ',', '.') }}</td>
                                <td class="px-4 py-3 text-xs text-gray-500">{{ $p->start_date->format('d/m/Y') }} – {{ $p->end_date->format('d/m/Y') }}</td>
                                <td class="px-4 py-3 text-center"><span class="status-pill {{ $cls }}">{{ $lab }}</span></td>
                                <td class="px-4 py-3 text-right">
                                    <form method="POST" action="{{ route('admin.discounts.product.destroy', $p) }}" onsubmit="return confirm('Hapus diskon ini?')">
                                        @csrf @method('DELETE')
                                        <button class="btn-action btn-delete">Hapus</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-10 text-center text-sm text-gray-500">Belum ada diskon produk</td></tr>
                        @endforelse
                    </tbody>
                </table>
                </div>
            </div>

            <div class="bg-white rounded-2xl border border-primary-100 p-5 shadow-sm">
                <h3 class="font-display font-semibold text-primary mb-3">Tambah Diskon Produk</h3>
                <form method="POST" action="{{ route('admin.discounts.product.store') }}" class="space-y-3">
                    @csrf
                    <select name="menu_id" required class="w-full rounded-lg border border-primary-100 px-3 py-2 text-sm">
                        <option value="">Pilih menu</option>
                        @foreach ($menus as $m) <option value="{{ $m->id }}">{{ $m->name }}</option> @endforeach
                    </select>
                    <select name="type" required class="w-full rounded-lg border border-primary-100 px-3 py-2 text-sm">
                        <option value="percentage">Persentase (%)</option>
                        <option value="nominal">Nominal (Rp)</option>
                    </select>
                    <input name="value" type="number" step="0.01" min="0" placeholder="Nilai diskon" required class="w-full rounded-lg border border-primary-100 px-3 py-2 text-sm" />
                    <input name="start_date" type="date" required class="w-full rounded-lg border border-primary-100 px-3 py-2 text-sm" />
                    <input name="end_date" type="date" required class="w-full rounded-lg border border-primary-100 px-3 py-2 text-sm" />
                    <button class="btn-action btn-add btn-action-lg btn-action-block">Simpan</button>
                </form>
            </div>
        </div>

    {{-- ============ PROMO CODES ============ --}}
    @elseif ($tab === 'promo')
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
            <div class="lg:col-span-2 bg-white rounded-2xl border border-primary-100 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                <table class="w-full text-sm min-w-[760px]">
                    <thead class="bg-primary-50 text-xs uppercase text-primary">
                        <tr>
                            <th class="text-left px-4 py-3">Kode</th>
                            <th class="text-left px-4 py-3">Tipe / Nilai</th>
                            <th class="text-right px-4 py-3">Pemakaian</th>
                            <th class="text-right px-4 py-3">Min Order</th>
                            <th class="text-left px-4 py-3">Periode</th>
                            <th class="text-center px-4 py-3">Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-primary-100">
                        @forelse ($promos as $pr)
                            @php [$lab, $cls] = $statusOf($pr); @endphp
                            <tr>
                                <td class="px-4 py-3 font-mono font-medium text-primary">{{ $pr->code }}</td>
                                <td class="px-4 py-3">{{ $pr->type }} · {{ $pr->type === 'percentage' ? rtrim(rtrim($pr->value, '0'), '.').'%' : 'Rp '.number_format((float) $pr->value, 0, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right">{{ $pr->used_count }}/{{ $pr->max_usage }}</td>
                                <td class="px-4 py-3 text-right">Rp {{ number_format((float) $pr->min_order, 0, ',', '.') }}</td>
                                <td class="px-4 py-3 text-xs text-gray-500">{{ $pr->start_date->format('d/m/Y') }} – {{ $pr->end_date->format('d/m/Y') }}</td>
                                <td class="px-4 py-3 text-center"><span class="status-pill {{ $cls }}">{{ $lab }}</span></td>
                                <td class="px-4 py-3 text-right">
                                    <form method="POST" action="{{ route('admin.discounts.promo.destroy', $pr) }}" onsubmit="return confirm('Hapus kode promo?')">
                                        @csrf @method('DELETE')
                                        <button class="btn-action btn-delete">Hapus</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-10 text-center text-sm text-gray-500">Belum ada kode promo</td></tr>
                        @endforelse
                    </tbody>
                </table>
                </div>
            </div>

            <div class="bg-white rounded-2xl border border-primary-100 p-5 shadow-sm">
                <h3 class="font-display font-semibold text-primary mb-3">Buat Kode Promo</h3>
                <form method="POST" action="{{ route('admin.discounts.promo.store') }}" class="space-y-3">
                    @csrf
                    <input name="code" placeholder="WELCOME10" required class="w-full rounded-lg border border-primary-100 px-3 py-2 text-sm font-mono" />
                    <select name="type" required class="w-full rounded-lg border border-primary-100 px-3 py-2 text-sm">
                        <option value="percentage">Persentase (%)</option>
                        <option value="nominal">Nominal (Rp)</option>
                    </select>
                    <input name="value" type="number" step="0.01" min="0" placeholder="Nilai" required class="w-full rounded-lg border border-primary-100 px-3 py-2 text-sm" />
                    <input name="max_usage" type="number" min="1" placeholder="Batas penggunaan" required class="w-full rounded-lg border border-primary-100 px-3 py-2 text-sm" />
                    <input name="min_order" type="number" min="0" placeholder="Minimum order (opsional)" class="w-full rounded-lg border border-primary-100 px-3 py-2 text-sm" />
                    <input name="start_date" type="date" required class="w-full rounded-lg border border-primary-100 px-3 py-2 text-sm" />
                    <input name="end_date" type="date" required class="w-full rounded-lg border border-primary-100 px-3 py-2 text-sm" />
                    <button class="btn-action btn-add btn-action-lg btn-action-block">Simpan</button>
                </form>
            </div>
        </div>

    {{-- ============ EVENT ============ --}}
    @else
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
            <div class="lg:col-span-2 bg-white rounded-2xl border border-primary-100 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                <table class="w-full text-sm min-w-[720px]">
                    <thead class="bg-primary-50 text-xs uppercase text-primary">
                        <tr>
                            <th class="text-left px-4 py-3">Event</th>
                            <th class="text-left px-4 py-3">Tipe / Nilai</th>
                            <th class="text-center px-4 py-3">Menu</th>
                            <th class="text-left px-4 py-3">Periode</th>
                            <th class="text-center px-4 py-3">Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-primary-100">
                        @forelse ($events as $e)
                            @php [$lab, $cls] = $statusOf($e); @endphp
                            <tr>
                                <td class="px-4 py-3 font-medium text-primary">{{ $e->name }}</td>
                                <td class="px-4 py-3">{{ $e->type }} · {{ $e->type === 'percentage' ? rtrim(rtrim($e->value, '0'), '.').'%' : 'Rp '.number_format((float) $e->value, 0, ',', '.') }}</td>
                                <td class="px-4 py-3 text-center">{{ $e->menus->count() }} menu</td>
                                <td class="px-4 py-3 text-xs text-gray-500">{{ $e->start_date->format('d/m/Y') }} – {{ $e->end_date->format('d/m/Y') }}</td>
                                <td class="px-4 py-3 text-center"><span class="status-pill {{ $cls }}">{{ $lab }}</span></td>
                                <td class="px-4 py-3 text-right">
                                    <form method="POST" action="{{ route('admin.discounts.event.destroy', $e) }}" onsubmit="return confirm('Hapus event?')">
                                        @csrf @method('DELETE')
                                        <button class="btn-action btn-delete">Hapus</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-10 text-center text-sm text-gray-500">Belum ada event diskon</td></tr>
                        @endforelse
                    </tbody>
                </table>
                </div>
            </div>

            <div class="bg-white rounded-2xl border border-primary-100 p-5 shadow-sm">
                <h3 class="font-display font-semibold text-primary mb-3">Buat Event Diskon</h3>
                <form method="POST" action="{{ route('admin.discounts.event.store') }}" class="space-y-3">
                    @csrf
                    <input name="name" placeholder="Nama event" required class="w-full rounded-lg border border-primary-100 px-3 py-2 text-sm" />
                    <select name="type" required class="w-full rounded-lg border border-primary-100 px-3 py-2 text-sm">
                        <option value="percentage">Persentase (%)</option>
                        <option value="nominal">Nominal (Rp)</option>
                    </select>
                    <input name="value" type="number" step="0.01" min="0" placeholder="Nilai" required class="w-full rounded-lg border border-primary-100 px-3 py-2 text-sm" />
                    <input name="start_date" type="date" required class="w-full rounded-lg border border-primary-100 px-3 py-2 text-sm" />
                    <input name="end_date" type="date" required class="w-full rounded-lg border border-primary-100 px-3 py-2 text-sm" />
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Pilih menu yang tercakup:</label>
                        <div class="max-h-40 overflow-y-auto border border-primary-100 rounded-lg p-2 space-y-1">
                            @foreach ($menus as $m)
                                <label class="flex items-center gap-2 text-sm">
                                    <input type="checkbox" name="menu_ids[]" value="{{ $m->id }}" />
                                    <span>{{ $m->name }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                    <button class="btn-action btn-add btn-action-lg btn-action-block">Simpan</button>
                </form>
            </div>
        </div>
    @endif
</div>
@endsection
