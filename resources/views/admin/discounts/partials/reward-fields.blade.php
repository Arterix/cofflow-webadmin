@php $num = fn ($v) => $v === null ? '' : rtrim(rtrim((string) $v, '0'), '.'); @endphp
<input name="name" value="{{ $reward?->name }}" placeholder="Nama reward (mis. Diskon 20%)" required class="{{ $input }}" />
<input name="description" value="{{ $reward?->description }}" placeholder="Deskripsi singkat (opsional)" class="{{ $input }}" />
<div class="grid grid-cols-2 gap-2">
    <select name="type" required class="{{ $input }}">
        <option value="percentage" @selected($reward?->type === 'percentage')>Persen (%)</option>
        <option value="nominal" @selected($reward?->type === 'nominal')>Nominal (Rp)</option>
    </select>
    <input name="value" type="number" step="0.01" min="1" value="{{ $num($reward?->value) }}" placeholder="Nilai" required class="{{ $input }}" />
</div>
<input name="max_discount" type="number" min="0" step="100" value="{{ $num($reward?->max_discount) }}" placeholder="Maks potongan Rp (khusus persen, opsional)" class="{{ $input }}" />
<input name="min_order" type="number" min="0" step="100" value="{{ $num($reward?->min_order) }}" placeholder="Minimum order Rp (opsional)" class="{{ $input }}" />
<div class="grid grid-cols-2 gap-2">
    <input name="points_cost" type="number" min="1" value="{{ $reward?->points_cost }}" placeholder="Harga poin" required class="{{ $input }}" />
    <input name="quota" type="number" min="1" value="{{ $reward?->quota }}" placeholder="Kuota (kosong = ∞)" class="{{ $input }}" />
</div>
<label class="block text-xs text-gray-500">Tier minimum
    <select name="min_tier" class="{{ $input }} mt-1">
        @foreach (\App\Models\PointReward::TIERS as $t)
            <option value="{{ $t }}" @selected(($reward?->min_tier ?? 'bronze') === $t)>{{ ucfirst($t) }}{{ $t === 'bronze' ? ' (semua member)' : '' }}</option>
        @endforeach
    </select>
</label>
