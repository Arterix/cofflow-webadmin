@extends('admin.layouts.app')

@section('title', 'Kategori · Cofflow Admin')
@section('page_title', 'Kategori Menu')
@section('page_sub', 'Kelompokkan menu ke dalam kategori')

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
    <div class="lg:col-span-2 bg-white rounded-2xl border border-primary-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
        <table class="w-full text-sm min-w-[560px]">
            <thead class="bg-primary-50 text-xs uppercase tracking-wide text-primary">
                <tr>
                    <th class="text-left px-4 py-3">Nama</th>
                    <th class="text-left px-4 py-3">Slug</th>
                    <th class="text-center px-4 py-3">Menu</th>
                    <th class="text-right px-4 py-3">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-primary-100">
                @forelse ($categories as $cat)
                    <tr>
                        <form method="POST" action="{{ route('admin.categories.update', $cat) }}" class="contents">
                            @csrf @method('PUT')
                            <td class="px-4 py-2"><input name="name" value="{{ $cat->name }}" class="w-full rounded-md border border-primary-100 px-2 py-1.5 text-sm" /></td>
                            <td class="px-4 py-2 text-gray-500 text-xs">{{ $cat->slug }}</td>
                            <td class="px-4 py-2 text-center">{{ $cat->menus_count }}</td>
                            <td class="px-4 py-2 text-right">
                                <button class="btn-action btn-edit">Simpan</button>
                        </form>
                                <form method="POST" action="{{ route('admin.categories.destroy', $cat) }}" class="inline" onsubmit="return confirm('Hapus kategori ini?')">
                                    @csrf @method('DELETE')
                                    <button class="btn-action btn-delete">Hapus</button>
                                </form>
                            </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-10 text-center text-sm text-gray-500">Belum ada kategori</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-primary-100 shadow-sm p-5">
        <h2 class="font-display font-semibold text-primary mb-3">Tambah Kategori</h2>
        <form method="POST" action="{{ route('admin.categories.store') }}" class="space-y-3">
            @csrf
            <input name="name" placeholder="Nama kategori" required class="w-full rounded-lg border border-primary-100 px-3 py-2 text-sm" />
            <button class="btn-action btn-add btn-action-lg btn-action-block">Tambah</button>
        </form>
    </div>
</div>
@endsection
