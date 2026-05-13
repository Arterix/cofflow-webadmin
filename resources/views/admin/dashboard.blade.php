@extends('admin.layouts.app')

@section('title', 'Dashboard · Cofflow Admin')
@section('page_title', 'Dashboard')
@section('page_sub', 'Ringkasan performa coffee shop hari ini')

@php
    $cards = [
        ['label' => 'Total Order Hari Ini', 'value' => $totalOrderToday, 'tone' => 'primary', 'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2'],
        ['label' => 'Revenue Hari Ini', 'value' => 'Rp '.number_format($revenueToday, 0, ',', '.'), 'tone' => 'accent', 'icon' => 'M12 8c-1.66 0-3 .67-3 1.5S10.34 11 12 11s3 .67 3 1.5S13.66 14 12 14m0-6V6m0 8v2'],
        ['label' => 'Order Aktif', 'value' => $activeOrders, 'tone' => 'primary', 'icon' => 'M13 10V3L4 14h7v7l9-11h-7z'],
        ['label' => 'Bahan Kritis', 'value' => $criticalIngredients, 'tone' => $criticalIngredients > 0 ? 'alert' : 'accent', 'icon' => 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z'],
    ];
@endphp

@section('content')
<div class="space-y-6">

    {{-- Summary cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        @foreach ($cards as $c)
            @php
                $bg = match($c['tone']) {
                    'alert'   => 'bg-alert-50 border-alert',
                    'accent'  => 'bg-accent-50 border-accent',
                    default   => 'bg-white border-primary-100',
                };
                $iconBg = match($c['tone']) {
                    'alert'  => 'bg-alert text-white',
                    'accent' => 'bg-accent text-primary',
                    default  => 'bg-primary text-white',
                };
            @endphp
            <div class="rounded-2xl border-l-4 {{ $bg }} p-5 shadow-sm">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-xs uppercase tracking-wide text-gray-500 font-medium">{{ $c['label'] }}</p>
                        <p class="mt-1 font-display text-2xl font-semibold text-primary">{{ $c['value'] }}</p>
                    </div>
                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl {{ $iconBg }}">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $c['icon'] }}"></path>
                        </svg>
                    </span>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Weekly sales chart + top menus --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        {{-- Bar chart 7 hari --}}
        <div class="lg:col-span-2 bg-white rounded-2xl border border-primary-100 p-5 shadow-sm">
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-display font-semibold text-primary">Penjualan 7 Hari Terakhir</h2>
                <span class="text-xs text-gray-500">Hanya order yang sudah dibayar</span>
            </div>

            <div class="overflow-x-auto -mx-1 px-1">
            <div class="grid grid-cols-7 gap-2 sm:gap-3 items-end h-64 min-w-[420px] sm:min-w-0" role="list" aria-label="Revenue 7 hari terakhir">
                @foreach ($weekly as $w)
                    @php
                        $maxR = max(1, (float) $maxRevenue);
                        $h = max(6, (int) round(($w['revenue'] / $maxR) * 200));
                        $isSelected = $w['date'] === $selectedDate;
                        if ($isSelected) {
                            $color = 'bg-accent ring-2 ring-primary';
                        } elseif ($w['is_today']) {
                            $color = 'bg-accent';
                        } else {
                            $color = 'bg-primary/80 hover:bg-primary';
                        }
                        $shortValue = $w['revenue'] >= 1_000_000
                            ? number_format($w['revenue'] / 1_000_000, 1, ',', '.').'jt'
                            : ($w['revenue'] >= 1000
                                ? number_format($w['revenue'] / 1000, 0, ',', '.').'rb'
                                : (string) (int) $w['revenue']);
                    @endphp
                    <div class="flex flex-col items-center gap-2" role="listitem">
                        <span class="text-[11px] font-medium text-primary tabular-nums" aria-hidden="true">{{ $shortValue }}</span>
                        <a href="{{ route('admin.dashboard', ['date' => $w['date']]) }}#stats"
                           class="w-full {{ $color }} rounded-t-lg relative group focus:outline-none focus-visible:ring-2 focus-visible:ring-accent transition"
                           style="height: {{ $h }}px"
                           aria-label="{{ $w['label'] }}: Rp {{ number_format($w['revenue'], 0, ',', '.') }}{{ $w['is_today'] ? ' (hari ini)' : '' }}{{ $isSelected ? ' (terpilih)' : '' }}"
                           @if ($isSelected) aria-current="true" @endif>
                            <span class="absolute -top-9 left-1/2 -translate-x-1/2 hidden group-hover:block group-focus:block bg-primary text-white text-xs px-2 py-1 rounded whitespace-nowrap pointer-events-none">
                                Rp {{ number_format($w['revenue'], 0, ',', '.') }}
                            </span>
                        </a>
                        <span class="text-xs {{ $isSelected ? 'text-primary font-semibold' : 'text-gray-500' }} text-center">
                            {{ $w['label'] }}{{ $w['is_today'] ? ' •' : '' }}
                        </span>
                    </div>
                @endforeach
            </div>
            </div>
        </div>

        {{-- Top menus --}}
        <div class="bg-white rounded-2xl border border-primary-100 p-5 shadow-sm">
            <h2 class="font-display font-semibold text-primary mb-4">Top 5 Menu (30 hari)</h2>

            @if ($topMenus->isEmpty())
                <div class="text-center py-10 text-sm text-gray-500">
                    <svg class="mx-auto h-10 w-10 text-gray-300 mb-2" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-6h6v6M3 7h18M5 7v13a1 1 0 001 1h12a1 1 0 001-1V7"/></svg>
                    Belum ada penjualan
                </div>
            @else
                <ul class="space-y-3">
                    @foreach ($topMenus as $m)
                        <li class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-primary text-white text-xs font-semibold">
                                    {{ $loop->iteration }}
                                </span>
                                <div>
                                    <div class="text-sm font-medium text-primary">{{ $m->menu?->name ?? '-' }}</div>
                                    <div class="text-xs text-gray-500">Rp {{ number_format((float) ($m->menu?->price ?? 0), 0, ',', '.') }}</div>
                                </div>
                            </div>
                            <span class="text-sm font-semibold text-accent-dark">{{ $m->total_qty }}×</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    {{-- ───── Statistik untuk candle terpilih ───── --}}
    @php
        $isToday = $selectedDate === now()->toDateString();
        $selectedLabel = \Carbon\Carbon::parse($selectedDate)->isoFormat('dddd, D MMMM YYYY');
        $peak = $selected['peak_hour'];
        $rateBadge = $selected['cancellation_rate'] >= 20 ? 'is-danger'
            : ($selected['cancellation_rate'] >= 10 ? 'is-warning' : 'is-success');
    @endphp

    <div id="stats" class="bg-white rounded-2xl border border-primary-100 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 border-b border-primary-100">
            <div>
                <h2 class="font-display font-semibold text-primary">Statistik Hari</h2>
                <p class="text-xs text-gray-500">
                    {{ $selectedLabel }}{{ $isToday ? ' · hari ini' : '' }} — klik candle di chart untuk ganti
                </p>
            </div>
            @if (! $isToday)
                <a href="{{ route('admin.dashboard') }}#stats" class="btn-action btn-info">← Kembali ke hari ini</a>
            @endif
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-px bg-primary-100">
            {{-- Total items sold --}}
            <div class="bg-white p-5">
                <p class="text-xs uppercase tracking-wide text-gray-500 font-medium">Total Item Terjual</p>
                <p class="mt-1 font-display text-2xl font-semibold text-primary tabular-nums">{{ number_format($selected['total_items_sold'], 0, ',', '.') }}</p>
                <p class="text-xs text-gray-500 mt-1">cup / unit (exclude cancelled)</p>
            </div>

            {{-- Net revenue --}}
            <div class="bg-white p-5">
                <p class="text-xs uppercase tracking-wide text-gray-500 font-medium">Net Revenue (Setelah Diskon)</p>
                <p class="mt-1 font-display text-2xl font-semibold text-accent-dark tabular-nums">Rp {{ number_format($selected['net_revenue'], 0, ',', '.') }}</p>
                <p class="text-xs text-gray-500 mt-1">Subtotal Rp {{ number_format($selected['subtotal'], 0, ',', '.') }}</p>
            </div>

            {{-- Discount total --}}
            <div class="bg-white p-5">
                <p class="text-xs uppercase tracking-wide text-gray-500 font-medium">Total Diskon Diberikan</p>
                <p class="mt-1 font-display text-2xl font-semibold {{ $selected['total_discount'] > 0 ? 'text-alert-dark' : 'text-primary' }} tabular-nums">
                    Rp {{ number_format($selected['total_discount'], 0, ',', '.') }}
                </p>
                <p class="text-xs text-gray-500 mt-1">
                    @if ($selected['subtotal'] > 0)
                        {{ number_format($selected['total_discount'] / $selected['subtotal'] * 100, 1) }}% dari subtotal
                    @else
                        —
                    @endif
                </p>
            </div>

            {{-- Peak hour --}}
            <div class="bg-white p-5">
                <p class="text-xs uppercase tracking-wide text-gray-500 font-medium">Jam Tersibuk</p>
                @if ($peak)
                    <p class="mt-1 font-display text-2xl font-semibold text-primary tabular-nums">
                        {{ str_pad((string) $peak['hour'], 2, '0', STR_PAD_LEFT) }}:00–{{ str_pad((string) ($peak['hour'] + 1), 2, '0', STR_PAD_LEFT) }}:00
                    </p>
                    <p class="text-xs text-gray-500 mt-1">{{ $peak['count'] }} order pada jam ini</p>
                @else
                    <p class="mt-1 font-display text-2xl font-semibold text-gray-400">—</p>
                    <p class="text-xs text-gray-500 mt-1">Belum ada order</p>
                @endif
            </div>

            {{-- Cancellation --}}
            <div class="bg-white p-5">
                <p class="text-xs uppercase tracking-wide text-gray-500 font-medium">Order Dibatalkan</p>
                <div class="mt-1 flex items-baseline gap-2 flex-wrap">
                    <p class="font-display text-2xl font-semibold text-primary tabular-nums">{{ $selected['cancelled_count'] }}</p>
                    <span class="status-pill {{ $rateBadge }}">{{ number_format($selected['cancellation_rate'], 1) }}%</span>
                </div>
                <p class="text-xs text-gray-500 mt-1">dari {{ $selected['total_orders'] }} total order</p>
            </div>

            {{-- Top 3 menus that day --}}
            <div class="bg-white p-5 sm:col-span-2 lg:col-span-1">
                <p class="text-xs uppercase tracking-wide text-gray-500 font-medium mb-2">Top 3 Menu Hari Ini</p>
                @if ($selected['top_menus']->isEmpty())
                    <p class="text-sm text-gray-400 italic">Belum ada penjualan</p>
                @else
                    <ol class="space-y-2">
                        @foreach ($selected['top_menus'] as $m)
                            <li class="flex items-center justify-between gap-3 text-sm">
                                <div class="flex items-center gap-2 min-w-0">
                                    <span class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-primary text-white text-xs font-semibold">{{ $loop->iteration }}</span>
                                    <span class="font-medium text-primary truncate">{{ $m->menu?->name ?? '-' }}</span>
                                </div>
                                <span class="text-xs font-semibold text-accent-dark tabular-nums shrink-0">{{ $m->total_qty }}×</span>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
