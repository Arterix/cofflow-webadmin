@extends('admin.layouts.app')

@section('title', 'Order Monitor · Cofflow Admin')
@section('page_title', 'Order Monitor')
@section('page_sub', 'Pantau pesanan masuk (read-only)')

@php
    $statusVariant = [
        'pending'    => 'is-warning',
        'processing' => 'is-info',
        'ready'      => 'is-success',
        'completed'  => 'is-strong',
        'cancelled'  => 'is-danger',
    ];
    $payVariant = [
        'paid'     => 'is-success',
        'unpaid'   => 'is-warning',
        'refunded' => 'is-danger',
    ];
@endphp

@section('content')
<div class="space-y-4">
    <form method="GET" class="flex flex-wrap gap-2">
        <select name="status" class="rounded-lg border border-primary-100 bg-white px-3 py-2 text-sm">
            <option value="">Semua status</option>
            @foreach (['pending','processing','ready','completed','cancelled'] as $s)
                <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>
            @endforeach
        </select>
        <select name="type" class="rounded-lg border border-primary-100 bg-white px-3 py-2 text-sm">
            <option value="">Semua tipe</option>
            <option value="preorder" @selected(request('type')==='preorder')>Preorder</option>
            <option value="walkin" @selected(request('type')==='walkin')>Walk-in</option>
        </select>
        <input type="date" name="date" value="{{ request('date') }}" class="rounded-lg border border-primary-100 bg-white px-3 py-2 text-sm" />
        <button class="btn-action btn-edit btn-action-lg">Filter</button>
    </form>

    <div class="bg-white rounded-2xl border border-primary-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
        <table class="w-full text-sm min-w-[720px]">
            <thead class="bg-primary-50 text-xs uppercase text-primary">
                <tr>
                    <th class="text-left px-4 py-3">#</th>
                    <th class="text-left px-4 py-3">Pelanggan</th>
                    <th class="text-left px-4 py-3">Tipe</th>
                    <th class="text-right px-4 py-3">Total</th>
                    <th class="text-center px-4 py-3">Status</th>
                    <th class="text-center px-4 py-3">Bayar</th>
                    <th class="text-left px-4 py-3">Waktu</th>
                    <th></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-primary-100">
                @forelse ($orders as $o)
                    <tr>
                        <td class="px-4 py-3 font-medium text-primary">#{{ $o->id }}</td>
                        <td class="px-4 py-3">{{ $o->user?->name ?? 'Walk-in' }}</td>
                        <td class="px-4 py-3 text-xs uppercase tracking-wide text-gray-500">{{ $o->order_type }}</td>
                        <td class="px-4 py-3 text-right">Rp {{ number_format((float) $o->total, 0, ',', '.') }}</td>
                        <td class="px-4 py-3 text-center">
                            <span class="status-pill {{ $statusVariant[$o->status] ?? 'is-muted' }}">{{ $o->status }}</span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="status-pill {{ $payVariant[$o->payment_status] ?? 'is-muted' }}">{{ $o->payment_status }}</span>
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-500">{{ $o->created_at->format('d/m H:i') }}</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('admin.orders.show', $o) }}" class="text-xs text-accent-dark hover:underline">Detail</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-12 text-center text-sm text-gray-500">
                            <svg class="mx-auto h-10 w-10 text-gray-300 mb-2" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                            Tidak ada order untuk filter ini
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>
    <div>{{ $orders->links() }}</div>
</div>
@endsection
