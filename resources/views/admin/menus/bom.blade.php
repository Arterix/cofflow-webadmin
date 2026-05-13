@extends('admin.layouts.app')

@section('title', 'BOM · '.$menu->name)
@section('page_title', 'BOM & Condiment — '.$menu->name)
@section('page_sub', 'Atur resep bahan baku dan grup condiment menu ini')

@section('content')
<form method="POST" action="{{ route('admin.menus.bom.update', $menu) }}" class="max-w-4xl space-y-5">
    @csrf
    @method('PUT')

    <div class="bg-white rounded-2xl border border-primary-100 p-6 shadow-sm">
        <h2 class="font-display font-semibold text-primary mb-4">Resep Bahan (BOM)</h2>

        <div id="bom-rows" class="space-y-2">
            @foreach (old('items', $menu->bomItems->map(fn ($b) => ['ingredient_id' => $b->ingredient_id, 'quantity' => $b->quantity])->toArray()) as $i => $row)
                <div class="flex flex-col sm:flex-row gap-2 sm:items-end bom-row">
                    <div class="flex-1">
                        <label class="block text-xs text-gray-500 mb-1">Bahan</label>
                        <select name="items[{{ $i }}][ingredient_id]" required class="w-full rounded-lg border border-primary-100 px-3 py-2 text-sm">
                            <option value="">Pilih bahan</option>
                            @foreach ($ingredients as $ing)
                                <option value="{{ $ing->id }}" @selected($row['ingredient_id'] == $ing->id)>{{ $ing->name }} ({{ $ing->unit }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="w-full sm:w-40">
                        <label class="block text-xs text-gray-500 mb-1">Jumlah</label>
                        <input type="number" step="0.001" min="0.001" name="items[{{ $i }}][quantity]" value="{{ $row['quantity'] }}" required class="w-full rounded-lg border border-primary-100 px-3 py-2 text-sm" />
                    </div>
                    <button type="button" onclick="this.closest('.bom-row').remove()" class="btn-action btn-delete">Hapus</button>
                </div>
            @endforeach
        </div>

        <button type="button" id="add-bom" class="btn-action btn-add btn-action-lg mt-3">+ Tambah Bahan</button>
    </div>

    <div class="bg-white rounded-2xl border border-primary-100 p-6 shadow-sm">
        <h2 class="font-display font-semibold text-primary mb-4">Condiment Group</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
            @foreach ($allGroups as $g)
                <label class="flex items-center gap-2 text-sm border border-primary-100 rounded-lg px-3 py-2 hover:bg-secondary/40">
                    <input type="checkbox" name="condiment_group_ids[]" value="{{ $g->id }}" @checked($menu->condimentGroups->contains($g->id)) />
                    <span>{{ $g->name }} <span class="text-xs text-gray-400">({{ $g->type }})</span></span>
                </label>
            @endforeach
        </div>
    </div>

    <div class="flex justify-end gap-2">
        <a href="{{ route('admin.menus.index') }}" class="btn-action btn-info btn-action-lg">Batal</a>
        <button type="submit" class="btn-action btn-edit btn-action-lg">Simpan</button>
    </div>
</form>

<template id="bom-template">
    <div class="flex flex-col sm:flex-row gap-2 sm:items-end bom-row">
        <div class="flex-1">
            <label class="block text-xs text-gray-500 mb-1">Bahan</label>
            <select name="items[__i__][ingredient_id]" required class="w-full rounded-lg border border-primary-100 px-3 py-2 text-sm">
                <option value="">Pilih bahan</option>
                @foreach ($ingredients as $ing)
                    <option value="{{ $ing->id }}">{{ $ing->name }} ({{ $ing->unit }})</option>
                @endforeach
            </select>
        </div>
        <div class="w-40">
            <label class="block text-xs text-gray-500 mb-1">Jumlah</label>
            <input type="number" step="0.001" min="0.001" name="items[__i__][quantity]" required class="w-full rounded-lg border border-primary-100 px-3 py-2 text-sm" />
        </div>
        <button type="button" onclick="this.closest('.bom-row').remove()" class="px-3 py-2 rounded-lg bg-alert-50 text-alert text-xs h-9">Hapus</button>
    </div>
</template>

<script>
    document.getElementById('add-bom').addEventListener('click', () => {
        const wrap = document.getElementById('bom-rows');
        const tpl = document.getElementById('bom-template').innerHTML;
        const idx = wrap.querySelectorAll('.bom-row').length + Date.now();
        wrap.insertAdjacentHTML('beforeend', tpl.replaceAll('__i__', idx));
    });
</script>
@endsection
