@extends('admin.layouts.app')

@section('title', 'Opname #'.$opname->id.' · Cofflow Admin')
@section('page_title', 'Opname #'.$opname->id)
@section('page_sub', 'Detail dan review opname stok')

@php
    $statusVariant = [
        'pending'  => 'is-warning',
        'approved' => 'is-success',
        'rejected' => 'is-danger',
    ];
@endphp

@section('content')
<div class="space-y-5">

    <div class="bg-white rounded-2xl border border-primary-100 p-5 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="space-y-1 text-sm">
                <div class="flex items-center gap-3 flex-wrap">
                    <span class="status-pill {{ $statusVariant[$opname->status] ?? 'is-muted' }}">{{ $opname->status }}</span>
                    <span class="text-xs uppercase tracking-wide text-gray-500">{{ $opname->shift_label ?? 'adhoc' }}</span>
                    <span class="text-xs text-gray-500">{{ $opname->shift_date->format('d/m/Y') }}</span>
                </div>
                <div><span class="text-gray-500">Dibuat oleh:</span> <span class="font-medium text-primary">{{ $opname->performedBy?->name ?? '-' }}</span></div>
                @if ($opname->reviewedBy)
                    <div><span class="text-gray-500">Direview oleh:</span> <span class="font-medium text-primary">{{ $opname->reviewedBy->name }}</span> · <span class="text-xs text-gray-500">{{ $opname->reviewed_at?->format('d/m/Y H:i') }}</span></div>
                @endif
                @if ($opname->notes)
                    <div class="text-gray-600"><span class="text-gray-500">Catatan kasir:</span> {{ $opname->notes }}</div>
                @endif
                @if ($opname->review_notes)
                    <div class="text-gray-600"><span class="text-gray-500">Catatan reviewer:</span> {{ $opname->review_notes }}</div>
                @endif
            </div>

            <div class="text-right">
                <p class="text-xs uppercase tracking-wide text-gray-500">Total |selisih|</p>
                <p class="font-display text-2xl font-semibold text-primary tabular-nums">
                    {{ rtrim(rtrim(number_format($totalAbsVariance, 3, '.', ''), '0'), '.') }}
                </p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-primary-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
        <table class="w-full text-sm min-w-[760px]">
            <thead class="bg-primary-50 text-xs uppercase text-primary">
                <tr>
                    <th class="text-left px-4 py-3">Bahan</th>
                    <th class="text-center px-4 py-3">Unit</th>
                    <th class="text-right px-4 py-3">Sistem</th>
                    <th class="text-right px-4 py-3">Fisik</th>
                    <th class="text-right px-4 py-3">Selisih</th>
                    <th class="text-left px-4 py-3">Alasan</th>
                    <th class="text-left px-4 py-3">Catatan</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-primary-100">
                @foreach ($opname->items as $item)
                    @php
                        $v = (float) $item->variance;
                        $pct = (float) $item->system_stock > 0 ? abs($v) / (float) $item->system_stock * 100 : ($v === 0.0 ? 0 : 100);
                        if ($v === 0.0) {
                            $rowCls = '';
                            $varCls = 'text-gray-500';
                        } elseif ($pct <= 5) {
                            $rowCls = 'bg-accent-50/30';
                            $varCls = 'text-accent-dark';
                        } else {
                            $rowCls = 'bg-alert-50/40';
                            $varCls = 'text-alert-dark font-semibold';
                        }
                    @endphp
                    <tr class="{{ $rowCls }}">
                        <td class="px-4 py-3 font-medium text-primary">{{ $item->ingredient?->name }}</td>
                        <td class="px-4 py-3 text-center text-xs text-gray-500">{{ $item->ingredient?->unit }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ rtrim(rtrim((string) $item->system_stock, '0'), '.') }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ rtrim(rtrim((string) $item->physical_stock, '0'), '.') }}</td>
                        <td class="px-4 py-3 text-right tabular-nums {{ $varCls }}">
                            {{ $v > 0 ? '+' : '' }}{{ rtrim(rtrim(number_format($v, 3, '.', ''), '0'), '.') }}
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-600">{{ $item->variance_reason ? ($reasons[$item->variance_reason] ?? $item->variance_reason) : '-' }}</td>
                        <td class="px-4 py-3 text-xs text-gray-600">{{ $item->notes ?? '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        </div>
    </div>

    @if ($opname->isPending())
        <div class="bg-white rounded-2xl border border-primary-100 p-5 shadow-sm space-y-4">
            <h2 class="font-display font-semibold text-primary">Review Opname</h2>

            @if ($hasVariance)
                <div class="rounded-lg bg-alert-50 border-l-4 border-alert p-3 text-sm text-primary">
                    <strong>Peringatan:</strong> Approve akan menimpa <code>current_stock</code> ingredients dengan nilai fisik. Pastikan hitungan benar.
                </div>
            @else
                <div class="rounded-lg bg-accent-50 border-l-4 border-accent p-3 text-sm text-primary">
                    Tidak ada selisih. Approve hanya untuk arsip.
                </div>
            @endif

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <form method="POST" action="{{ route('admin.opnames.approve', $opname) }}" class="space-y-3"
                      onsubmit="return confirm('Setujui opname dan terapkan ke stok?')">
                    @csrf
                    <label class="block text-sm font-medium text-primary">Catatan approve (opsional)</label>
                    <textarea name="review_notes" rows="2" class="w-full rounded-lg border border-primary-100 px-3 py-2 text-sm"></textarea>
                    <button type="submit" class="btn-action btn-add btn-action-lg btn-action-block">Approve &amp; Terapkan ke Stok</button>
                </form>

                <form method="POST" action="{{ route('admin.opnames.reject', $opname) }}" class="space-y-3"
                      onsubmit="return confirm('Tolak opname? Stok tidak akan berubah.')">
                    @csrf
                    <label class="block text-sm font-medium text-primary">Alasan ditolak (wajib)</label>
                    <textarea name="review_notes" rows="2" required class="w-full rounded-lg border border-primary-100 px-3 py-2 text-sm"></textarea>
                    <button type="submit" class="btn-action btn-delete btn-action-lg btn-action-block">Tolak Opname</button>
                </form>
            </div>
        </div>
    @endif

    <div>
        <a href="{{ route('admin.opnames.index') }}" class="btn-action btn-info btn-action-lg">← Kembali ke daftar</a>
    </div>
</div>
@endsection
