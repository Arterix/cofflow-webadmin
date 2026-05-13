@extends('admin.layouts.app')

@php $isEdit = $menu->exists; @endphp

@section('title', ($isEdit ? 'Edit' : 'Tambah').' Menu · Cofflow')
@section('page_title', $isEdit ? 'Edit Menu' : 'Tambah Menu')
@section('page_sub', $isEdit ? 'Perbarui detail menu' : 'Tambah menu baru ke katalog')

@section('content')
<form method="POST" action="{{ $isEdit ? route('admin.menus.update', $menu) : route('admin.menus.store') }}" enctype="multipart/form-data" class="max-w-3xl space-y-5">
    @csrf
    @if ($isEdit) @method('PUT') @endif

    <div class="bg-white rounded-2xl border border-primary-100 p-6 shadow-sm space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-primary mb-1">Nama menu</label>
                <input name="name" value="{{ old('name', $menu->name) }}" required class="w-full rounded-lg border border-primary-100 px-3 py-2 text-sm" />
            </div>
            <div>
                <label class="block text-sm font-medium text-primary mb-1">Kategori</label>
                <select name="menu_category_id" required class="w-full rounded-lg border border-primary-100 px-3 py-2 text-sm">
                    <option value="">Pilih kategori</option>
                    @foreach ($categories as $c)
                        <option value="{{ $c->id }}" @selected(old('menu_category_id', $menu->menu_category_id) == $c->id)>{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-primary mb-1">Harga (IDR)</label>
                <input name="price" type="number" step="100" min="0" value="{{ old('price', $menu->price) }}" required class="w-full rounded-lg border border-primary-100 px-3 py-2 text-sm" />
            </div>
            <div>
                <label class="block text-sm font-medium text-primary mb-1">Foto menu (opsional)</label>
                <input name="image" type="file" accept="image/*" class="w-full text-sm" />
                @if ($isEdit && $menu->image_url)
                    <img src="{{ $menu->image_url }}" alt="" class="mt-2 h-16 w-16 rounded-lg object-cover" />
                @endif
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-primary mb-1">Deskripsi</label>
            <textarea name="description" rows="3" class="w-full rounded-lg border border-primary-100 px-3 py-2 text-sm">{{ old('description', $menu->description) }}</textarea>
        </div>

        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $menu->is_active ?? true)) />
            Tampilkan ke pelanggan (aktif)
        </label>
    </div>

    <div class="flex flex-col-reverse sm:flex-row gap-2 sm:justify-end">
        <a href="{{ route('admin.menus.index') }}" class="btn-action btn-info btn-action-lg">Batal</a>
        <button type="submit" class="btn-action {{ $isEdit ? 'btn-edit' : 'btn-add' }} btn-action-lg">
            {{ $isEdit ? 'Simpan Perubahan' : 'Buat Menu' }}
        </button>
    </div>
</form>
@endsection
