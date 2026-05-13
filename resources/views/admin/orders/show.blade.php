@extends('admin.layouts.app')

@section('title', 'Order #'.$order->id.' · Cofflow Admin')
@section('page_title', 'Order #'.$order->id)
@section('page_sub', 'Detail pesanan')

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
    <div class="lg:col-span-2 space-y-4">
        <div class="bg-white rounded-2xl border border-primary-100 p-5 shadow-sm">
            <h2 class="font-display font-semibold text-primary mb-3">Item Pesanan</h2>
            <div class="divide-y divide-primary-100">
                @foreach ($order->items as $it)
                    <div class="py-3 flex items-start justify-between">
                        <div>
                            <div class="font-medium text-primary">{{ $it->menu?->name }} × {{ $it->quantity }}</div>
                            @if ($it->condiments->count())
                                <div class="text-xs text-gray-500">{{ $it->condiments->pluck('option_name')->join(', ') }}</div>
                            @endif
                            @if ($it->notes)
                                <div class="text-xs text-gray-500 italic">"{{ $it->notes }}"</div>
                            @endif
                        </div>
                        <div class="text-right text-sm">
                            <div>Rp {{ number_format((float) $it->unit_price, 0, ',', '.') }}</div>
                            @if ((float) $it->applied_discount > 0)
                                <div class="text-xs text-accent-dark">−Rp {{ number_format((float) $it->applied_discount, 0, ',', '.') }}</div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-4 pt-4 border-t border-primary-100 text-sm space-y-1">
                <div class="flex justify-between"><span class="text-gray-500">Subtotal</span><span>Rp {{ number_format((float) $order->subtotal, 0, ',', '.') }}</span></div>
                <div class="flex justify-between"><span class="text-gray-500">Diskon</span><span class="text-accent-dark">−Rp {{ number_format((float) $order->discount_amount, 0, ',', '.') }}</span></div>
                <div class="flex justify-between font-display font-semibold text-primary text-base pt-1"><span>Total</span><span>Rp {{ number_format((float) $order->total, 0, ',', '.') }}</span></div>
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-primary-100 p-5 shadow-sm">
            <h2 class="font-display font-semibold text-primary mb-3">Riwayat Status</h2>
            @if ($order->statusLogs->isEmpty())
                <p class="text-sm text-gray-500">Belum ada perubahan status.</p>
            @else
                <ul class="space-y-2">
                    @foreach ($order->statusLogs as $log)
                        <li class="text-sm flex justify-between border-l-2 border-accent pl-3">
                            <span><span class="font-medium text-primary">{{ $log->status }}</span> @if ($log->notes) <span class="text-gray-500">— {{ $log->notes }}</span> @endif</span>
                            <span class="text-xs text-gray-500">{{ $log->changedBy?->name }} · {{ $log->created_at->format('d/m H:i') }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    <div class="space-y-4">
        <div class="bg-white rounded-2xl border border-primary-100 p-5 shadow-sm space-y-2 text-sm">
            <h2 class="font-display font-semibold text-primary mb-2">Info</h2>
            <div class="flex justify-between"><span class="text-gray-500">Pelanggan</span><span>{{ $order->user?->name ?? 'Walk-in' }}</span></div>
            <div class="flex justify-between"><span class="text-gray-500">Kasir</span><span>{{ $order->cashier?->name ?? '-' }}</span></div>
            <div class="flex justify-between"><span class="text-gray-500">Tipe</span><span>{{ $order->order_type }}</span></div>
            <div class="flex justify-between"><span class="text-gray-500">Antrian</span><span>#{{ $order->queue_number }}</span></div>
            <div class="flex justify-between"><span class="text-gray-500">Status</span><span>{{ $order->status }}</span></div>
            <div class="flex justify-between"><span class="text-gray-500">Bayar</span><span>{{ $order->payment_method }} · {{ $order->payment_status }}</span></div>
            @if ($order->promo_code)
                <div class="flex justify-between"><span class="text-gray-500">Kode promo</span><span class="font-mono">{{ $order->promo_code }}</span></div>
            @endif
            @if ($order->qr_code_url)
                <div class="pt-2"><a href="{{ $order->qr_code_url }}" target="_blank" class="text-accent-dark text-xs">Lihat QR Code →</a></div>
            @endif
            @if ($order->va_number)
                <div class="pt-2 text-xs">VA: <span class="font-mono">{{ $order->va_number }}</span> ({{ $order->payment_channel }})</div>
            @endif
            @if ($order->notes)
                <div class="pt-2 italic text-gray-500">"{{ $order->notes }}"</div>
            @endif
        </div>
        <a href="{{ route('admin.orders.index') }}" class="block text-center text-sm text-gray-500 hover:text-primary">← Kembali ke daftar</a>
    </div>
</div>
@endsection
