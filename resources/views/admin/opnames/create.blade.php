@extends('admin.layouts.app')

@section('title', 'Opname Baru · Cofflow Admin')
@section('page_title', 'Opname Stok Baru')
@section('page_sub', 'Catat stok fisik bahan baku end-of-shift')

@php
    $reasons = \App\Models\StockOpnameItem::REASONS;
@endphp

@section('content')
<form method="POST" action="{{ route('admin.opnames.store') }}" class="space-y-5">
    @csrf

    <div class="bg-white rounded-2xl border border-primary-100 p-5 shadow-sm space-y-4">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-primary mb-1">Tanggal shift</label>
                <input type="date" name="shift_date" value="{{ old('shift_date', now()->format('Y-m-d')) }}" required
                       class="w-full rounded-lg border border-primary-100 px-3 py-2 text-sm" />
            </div>
            <div>
                <label class="block text-sm font-medium text-primary mb-1">Label shift</label>
                <select name="shift_label" class="w-full rounded-lg border border-primary-100 px-3 py-2 text-sm">
                    <option value="adhoc">Ad-hoc</option>
                    <option value="morning">Pagi</option>
                    <option value="evening">Sore</option>
                    <option value="closing">Tutup toko</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-primary mb-1">Catatan (opsional)</label>
                <input name="notes" placeholder="Catatan umum" class="w-full rounded-lg border border-primary-100 px-3 py-2 text-sm" />
            </div>
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-primary-100 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-primary-100 flex items-center justify-between">
            <h2 class="font-display font-semibold text-primary">Hitung Stok Fisik</h2>
            <span class="text-xs text-gray-500">Isi hanya bahan yang dihitung — kosongkan sisanya</span>
        </div>

        <div class="overflow-x-auto">
        <table class="w-full text-sm min-w-[720px]">
            <thead class="bg-primary-50 text-xs uppercase text-primary">
                <tr>
                    <th class="text-left px-4 py-3">Bahan</th>
                    <th class="text-center px-4 py-3">Unit</th>
                    <th class="text-right px-4 py-3">Sistem</th>
                    <th class="text-right px-4 py-3 w-40">Fisik</th>
                    <th class="text-left px-4 py-3 w-44">Alasan selisih</th>
                    <th class="text-left px-4 py-3">Catatan</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-primary-100" id="opname-rows">
                @foreach ($ingredients as $idx => $ing)
                    <tr data-row>
                        <td class="px-4 py-3 font-medium text-primary">{{ $ing->name }}</td>
                        <td class="px-4 py-3 text-center text-xs text-gray-500">{{ $ing->unit }}</td>
                        <td class="px-4 py-3 text-right text-gray-700 tabular-nums">{{ rtrim(rtrim((string) $ing->current_stock, '0'), '.') }}</td>
                        <td class="px-4 py-3 text-right">
                            <input type="hidden" name="items[{{ $idx }}][ingredient_id]" value="{{ $ing->id }}" data-hidden />
                            <input type="number" step="0.001" min="0" name="items[{{ $idx }}][physical_stock]"
                                   data-physical data-system="{{ (float) $ing->current_stock }}"
                                   placeholder="Skip" class="w-full rounded-md border border-primary-100 px-2 py-1.5 text-sm text-right tabular-nums" />
                            <p class="text-xs mt-1 text-right" data-variance></p>
                        </td>
                        <td class="px-4 py-3">
                            <select name="items[{{ $idx }}][variance_reason]" class="w-full rounded-md border border-primary-100 px-2 py-1.5 text-xs">
                                <option value="">—</option>
                                @foreach ($reasons as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td class="px-4 py-3">
                            <input name="items[{{ $idx }}][notes]" placeholder="Optional" class="w-full rounded-md border border-primary-100 px-2 py-1.5 text-xs" />
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        </div>
    </div>

    <div class="flex justify-end gap-2">
        <a href="{{ route('admin.opnames.index') }}" class="btn-action btn-info btn-action-lg">Batal</a>
        <button type="submit" class="btn-action btn-add btn-action-lg">Submit untuk Review</button>
    </div>
</form>

<script>
    // Live variance compute + strip rows with no physical input on submit.
    (function () {
        const rows = document.querySelectorAll('[data-row]');
        rows.forEach((row) => {
            const physical = row.querySelector('[data-physical]');
            const variance = row.querySelector('[data-variance]');
            physical.addEventListener('input', () => {
                if (physical.value === '') { variance.textContent = ''; return; }
                const diff = parseFloat(physical.value) - parseFloat(physical.dataset.system);
                if (Number.isNaN(diff)) { variance.textContent = ''; return; }
                const sign = diff > 0 ? '+' : '';
                variance.textContent = sign + diff.toFixed(3).replace(/\.?0+$/, '');
                variance.className = 'text-xs mt-1 text-right ' + (diff === 0 ? 'text-gray-400' : (diff < 0 ? 'text-alert-dark' : 'text-accent-dark'));
            });
        });

        document.querySelector('form').addEventListener('submit', (e) => {
            let filled = 0;
            rows.forEach((row) => {
                const physical = row.querySelector('[data-physical]');
                if (physical.value === '') {
                    row.querySelectorAll('input, select').forEach((el) => el.disabled = true);
                } else {
                    filled++;
                }
            });
            if (filled === 0) {
                e.preventDefault();
                alert('Isi minimal 1 bahan untuk membuat opname.');
                rows.forEach((row) => row.querySelectorAll('input, select').forEach((el) => el.disabled = false));
            }
        });
    })();
</script>
@endsection
