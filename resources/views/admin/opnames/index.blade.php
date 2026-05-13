@extends('admin.layouts.app')

@section('title', 'Opname Stok · Cofflow Admin')
@section('page_title', 'Opname Stok')
@section('page_sub', 'Cek fisik bahan baku end-of-shift')

@php
    $statusVariant = [
        'pending'  => 'is-warning',
        'approved' => 'is-success',
        'rejected' => 'is-danger',
    ];
@endphp

@section('content')
<div class="space-y-5">

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="rounded-2xl border-l-4 bg-secondary-dark/40 border-primary p-4">
            <p class="text-xs uppercase tracking-wide text-gray-600 font-medium">Menunggu Review</p>
            <p class="mt-1 font-display text-2xl font-semibold text-primary">{{ $summary['pending'] }}</p>
        </div>
        <div class="rounded-2xl border-l-4 bg-accent-50 border-accent p-4">
            <p class="text-xs uppercase tracking-wide text-gray-600 font-medium">Disetujui Hari Ini</p>
            <p class="mt-1 font-display text-2xl font-semibold text-primary">{{ $summary['approved_today'] }}</p>
        </div>
        <div class="rounded-2xl border-l-4 bg-alert-50 border-alert p-4">
            <p class="text-xs uppercase tracking-wide text-gray-600 font-medium">Ditolak Hari Ini</p>
            <p class="mt-1 font-display text-2xl font-semibold text-primary">{{ $summary['rejected_today'] }}</p>
        </div>
    </div>

    <div class="flex flex-wrap gap-3 justify-between items-center">
        <div class="flex flex-wrap gap-2">
            @foreach ([['','Semua'],['pending','Pending'],['approved','Disetujui'],['rejected','Ditolak']] as [$k, $label])
                <a href="{{ route('admin.opnames.index', $k ? ['status' => $k] : []) }}"
                   class="px-3 py-1.5 text-xs rounded-full border {{ ($status ?? '') === $k ? 'bg-primary text-white border-primary' : 'bg-white border-primary-100 text-primary' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>
        <a href="{{ route('admin.opnames.create') }}" class="btn-action btn-add btn-action-lg">+ Opname Baru</a>
    </div>

    <div class="bg-white rounded-2xl border border-primary-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
        <table class="w-full text-sm min-w-[820px]">
            <thead class="bg-primary-50 text-xs uppercase text-primary">
                <tr>
                    <th class="text-left px-4 py-3">#</th>
                    <th class="text-left px-4 py-3">Tanggal</th>
                    <th class="text-left px-4 py-3">Shift</th>
                    <th class="text-left px-4 py-3">Kasir</th>
                    <th class="text-center px-4 py-3">Item</th>
                    <th class="text-center px-4 py-3">Status</th>
                    <th class="text-left px-4 py-3">Reviewer</th>
                    <th></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-primary-100">
                @forelse ($opnames as $o)
                    <tr>
                        <td class="px-4 py-3 font-medium text-primary">#{{ $o->id }}</td>
                        <td class="px-4 py-3">{{ $o->shift_date->format('d/m/Y') }}</td>
                        <td class="px-4 py-3 text-xs uppercase tracking-wide text-gray-500">{{ $o->shift_label ?? '-' }}</td>
                        <td class="px-4 py-3">{{ $o->performedBy?->name ?? '-' }}</td>
                        <td class="px-4 py-3 text-center">{{ $o->items_count }}</td>
                        <td class="px-4 py-3 text-center">
                            <span class="status-pill {{ $statusVariant[$o->status] ?? 'is-muted' }}">{{ $o->status }}</span>
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-500">{{ $o->reviewedBy?->name ?? '-' }}</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('admin.opnames.show', $o) }}" class="btn-action btn-info">Detail</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-12 text-center text-sm text-gray-500">
                            <svg class="mx-auto h-10 w-10 text-gray-300 mb-2" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2m-6 8l2 2 4-4"/></svg>
                            Belum ada opname. <a href="{{ route('admin.opnames.create') }}" class="text-accent-dark font-medium">Buat opname pertama</a>.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>

    <div>{{ $opnames->links() }}</div>
</div>
@endsection
