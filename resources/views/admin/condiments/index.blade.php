@extends('admin.layouts.app')

@section('title', 'Condiment · Cofflow Admin')
@section('page_title', 'Condiment')
@section('page_sub', 'Atur grup add-on / pilihan untuk menu')

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
    <div class="lg:col-span-2 space-y-4">
        @forelse ($groups as $group)
            <details class="bg-white rounded-2xl border border-primary-100 shadow-sm" open>
                <summary class="cursor-pointer px-5 py-4 flex items-center justify-between">
                    <div>
                        <h3 class="font-display font-semibold text-primary">{{ $group->name }}</h3>
                        <p class="text-xs text-gray-500">{{ $group->type }} · {{ $group->is_required ? 'wajib' : 'opsional' }}</p>
                    </div>
                    <form method="POST" action="{{ route('admin.condiments.groups.destroy', $group) }}" onsubmit="return confirm('Hapus group ini beserta semua opsinya?')">
                        @csrf @method('DELETE')
                        <button class="btn-action btn-delete">Hapus group</button>
                    </form>
                </summary>

                <div class="border-t border-primary-100 px-5 py-4 space-y-2"
                     data-options-list
                     data-group-id="{{ $group->id }}"
                     data-reorder-url="{{ route('admin.condiments.groups.options.reorder', $group) }}">
                    @foreach ($group->options as $opt)
                        <div class="option-row flex items-center justify-between text-sm gap-2 px-2 py-1.5 rounded-lg border border-transparent hover:border-primary-100 hover:bg-secondary/40 transition"
                             draggable="true"
                             data-option-id="{{ $opt->id }}">
                            <div class="flex items-center gap-3 min-w-0">
                                <span class="drag-handle cursor-grab active:cursor-grabbing text-gray-400 hover:text-primary shrink-0" title="Tarik untuk urut ulang" aria-label="Drag handle">
                                    <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                        <circle cx="6" cy="4" r="1.4"/><circle cx="6" cy="10" r="1.4"/><circle cx="6" cy="16" r="1.4"/>
                                        <circle cx="14" cy="4" r="1.4"/><circle cx="14" cy="10" r="1.4"/><circle cx="14" cy="16" r="1.4"/>
                                    </svg>
                                </span>
                                <div class="min-w-0">
                                    <span class="font-medium text-primary">{{ $opt->name }}</span>
                                    @if ((float) $opt->additional_price > 0)
                                        <span class="ml-2 text-xs text-accent-dark">+ Rp {{ number_format((float) $opt->additional_price, 0, ',', '.') }}</span>
                                    @endif
                                </div>
                            </div>
                            <div class="flex items-center gap-1 shrink-0">
                                <details class="js-popover inline-block text-left relative">
                                    <summary class="btn-action btn-edit cursor-pointer list-none">Edit</summary>
                                    <form method="POST" action="{{ route('admin.condiments.options.update', $opt) }}"
                                          class="absolute right-0 mt-1 z-10 bg-white border border-primary-100 rounded-lg p-3 w-64 space-y-2 shadow-lg">
                                        @csrf @method('PUT')
                                        <label class="block text-xs text-gray-500">Nama opsi</label>
                                        <input name="name" value="{{ $opt->name }}" required class="w-full rounded-md border border-primary-100 px-2 py-1.5 text-sm" />
                                        <label class="block text-xs text-gray-500">Harga tambahan (Rp)</label>
                                        <input name="additional_price" type="number" step="100" min="0" value="{{ (int) (float) $opt->additional_price }}" class="w-full rounded-md border border-primary-100 px-2 py-1.5 text-sm" />
                                        <button class="btn-action btn-edit btn-action-block">Simpan</button>
                                    </form>
                                </details>
                                <form method="POST" action="{{ route('admin.condiments.options.destroy', $opt) }}">
                                    @csrf @method('DELETE')
                                    <button class="btn-action btn-delete">Hapus</button>
                                </form>
                            </div>
                        </div>
                    @endforeach

                    <form method="POST" action="{{ route('admin.condiments.groups.options.store', $group) }}" class="flex flex-wrap gap-2 pt-3 border-t border-primary-100">
                        @csrf
                        <input name="name" placeholder="Nama opsi" required class="flex-1 min-w-[140px] rounded-md border border-primary-100 px-2 py-1.5 text-sm" />
                        <input name="additional_price" type="number" step="100" min="0" value="0" class="w-28 sm:w-32 rounded-md border border-primary-100 px-2 py-1.5 text-sm" />
                        <button class="btn-action btn-add">+ Opsi</button>
                    </form>
                </div>
            </details>
        @empty
            <div class="bg-white rounded-2xl border border-primary-100 p-10 text-center text-sm text-gray-500">
                Belum ada condiment group
            </div>
        @endforelse
    </div>

    <div class="bg-white rounded-2xl border border-primary-100 p-5 shadow-sm h-fit">
        <h2 class="font-display font-semibold text-primary mb-3">Tambah Group</h2>
        <form method="POST" action="{{ route('admin.condiments.groups.store') }}" class="space-y-3">
            @csrf
            <input name="name" placeholder="Nama group (e.g. Ukuran)" required class="w-full rounded-lg border border-primary-100 px-3 py-2 text-sm" />
            <select name="type" required class="w-full rounded-lg border border-primary-100 px-3 py-2 text-sm">
                <option value="single_select">Single select (pilih satu)</option>
                <option value="multi_select">Multi select (boleh banyak)</option>
            </select>
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="is_required" value="1" />
                Wajib dipilih saat order
            </label>
            <button class="btn-action btn-add btn-action-lg btn-action-block">Tambah Group</button>
        </form>
    </div>
</div>

<script>
    // Drag-to-reorder for condiment options within each group.
    (function () {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

        document.querySelectorAll('[data-options-list]').forEach((list) => {
            let dragging = null;

            list.querySelectorAll('.option-row').forEach((row) => {
                row.addEventListener('dragstart', (e) => {
                    dragging = row;
                    row.classList.add('opacity-40');
                    e.dataTransfer.effectAllowed = 'move';
                    e.dataTransfer.setData('text/plain', row.dataset.optionId);
                });

                row.addEventListener('dragend', () => {
                    row.classList.remove('opacity-40');
                    list.querySelectorAll('.option-row').forEach((r) => r.classList.remove('ring-2', 'ring-accent'));
                    dragging = null;
                });

                row.addEventListener('dragover', (e) => {
                    if (!dragging || dragging === row) return;
                    e.preventDefault();
                    e.dataTransfer.dropEffect = 'move';
                    const rect = row.getBoundingClientRect();
                    const after = (e.clientY - rect.top) > rect.height / 2;
                    row.parentNode.insertBefore(dragging, after ? row.nextSibling : row);
                });

                row.addEventListener('drop', (e) => {
                    e.preventDefault();
                });
            });

            // Persist order after each drop event bubbles.
            list.addEventListener('drop', () => persist(list));
            // Touch-friendly fallback: persist after dragend too.
            list.addEventListener('dragend', () => persist(list));
        });

        let pending = null;
        function persist(list) {
            clearTimeout(pending);
            pending = setTimeout(() => {
                const ids = Array.from(list.querySelectorAll('.option-row')).map((r) => Number(r.dataset.optionId));
                fetch(list.dataset.reorderUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ option_ids: ids }),
                }).catch((err) => console.error('Reorder failed', err));
            }, 150);
        }
    })();
</script>
@endsection
