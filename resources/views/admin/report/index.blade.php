@extends('admin.layouts.app')

@section('title', 'Laporan Penjualan · Cofflow Admin')
@section('page_title', 'Laporan Penjualan')
@section('page_sub', 'Filter rentang waktu untuk rekap penjualan')

@section('content')
<div class="space-y-5">
    <form method="GET" class="bg-white rounded-2xl border border-primary-100 p-4 shadow-sm flex flex-wrap items-end gap-3">
        <div>
            <label class="block text-xs text-gray-500 mb-1">Dari</label>
            <input type="date" name="start" value="{{ $start }}" class="rounded-lg border border-primary-100 px-3 py-2 text-sm" />
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">Sampai</label>
            <input type="date" name="end" value="{{ $end }}" class="rounded-lg border border-primary-100 px-3 py-2 text-sm" />
        </div>
        <button class="btn-action btn-edit btn-action-lg">Generate</button>
    </form>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div class="bg-white rounded-2xl border border-primary-100 p-5 shadow-sm">
            <div class="text-xs uppercase text-gray-500">Total Order</div>
            <div class="font-display text-2xl font-semibold text-primary">{{ $totalOrder }}</div>
        </div>
        <div class="bg-white rounded-2xl border border-primary-100 p-5 shadow-sm">
            <div class="text-xs uppercase text-gray-500">Total Revenue</div>
            <div class="font-display text-2xl font-semibold text-primary">Rp {{ number_format($totalRevenue, 0, ',', '.') }}</div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        <div class="bg-white rounded-2xl border border-primary-100 p-5 shadow-sm">
            <h2 class="font-display font-semibold text-primary mb-3">Breakdown Pembayaran</h2>
            <table class="w-full text-sm">
                <thead class="text-xs text-gray-500">
                    <tr><th class="text-left py-2">Metode</th><th class="text-right">Order</th><th class="text-right">Revenue</th></tr>
                </thead>
                <tbody class="divide-y divide-primary-100">
                    @forelse ($payment as $p)
                        <tr>
                            <td class="py-2">{{ $p->payment_method }}</td>
                            <td class="py-2 text-right">{{ $p->count }}</td>
                            <td class="py-2 text-right">Rp {{ number_format((float) $p->revenue, 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="py-6 text-center text-gray-500 text-sm">Tidak ada data</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="bg-white rounded-2xl border border-primary-100 p-5 shadow-sm">
            <h2 class="font-display font-semibold text-primary mb-3">Top 10 Menu</h2>
            <ol class="space-y-2 text-sm">
                @forelse ($topMenus as $m)
                    <li class="flex items-center justify-between border-b border-primary-100 pb-1.5">
                        <span class="text-primary font-medium">{{ $loop->iteration }}. {{ $m->menu?->name }}</span>
                        <span><span class="text-accent-dark font-semibold">{{ $m->total_qty }}×</span> · Rp {{ number_format((float) $m->revenue, 0, ',', '.') }}</span>
                    </li>
                @empty
                    <li class="py-6 text-center text-gray-500">Tidak ada data</li>
                @endforelse
            </ol>
        </div>
    </div>
</div>
@endsection
