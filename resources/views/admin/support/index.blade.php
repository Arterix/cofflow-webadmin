@extends('admin.layouts.app')

@section('title', 'Chat CS · Konco Space Admin')
@section('page_title', 'Chat Customer Service')
@section('page_sub', 'Balas pertanyaan customer dari aplikasi')

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
    <div class="bg-white rounded-2xl border border-primary-100 shadow-sm overflow-hidden {{ $active ? 'hidden lg:block' : '' }}">
        <div class="px-4 py-3 border-b border-primary-100 bg-primary-50 text-xs uppercase tracking-wide text-primary font-medium">
            Percakapan
        </div>
        <ul class="divide-y divide-primary-100 max-h-[70vh] overflow-y-auto">
            @forelse ($threads as $t)
                @php $isActive = $active && $active->id === $t->customer_id; @endphp
                <li>
                    <a href="{{ route('admin.support.index', ['customer' => $t->customer_id]) }}"
                       class="block px-4 py-3 hover:bg-primary-50 {{ $isActive ? 'bg-primary-50' : '' }}">
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-medium text-primary truncate">{{ $t->customer?->name ?? 'User #'.$t->customer_id }}</span>
                            @if ($t->unread > 0)
                                <span class="shrink-0 inline-flex min-w-5 h-5 px-1.5 items-center justify-center rounded-full bg-alert text-white text-[10px] font-bold">{{ $t->unread }}</span>
                            @endif
                        </div>
                        <p class="text-xs text-gray-500 truncate mt-0.5">
                            {{ $t->from_customer ? '' : 'Anda: ' }}{{ $t->body }}
                        </p>
                        <p class="text-[10px] text-gray-400 mt-0.5">{{ $t->created_at->format('d/m/Y H:i') }}</p>
                    </a>
                </li>
            @empty
                <li class="px-4 py-10 text-center text-sm text-gray-500">Belum ada pesan dari customer</li>
            @endforelse
        </ul>
    </div>

    <div class="lg:col-span-2 bg-white rounded-2xl border border-primary-100 shadow-sm flex flex-col min-h-[60vh] {{ $active ? '' : 'hidden lg:flex' }}">
        @if ($active)
            <div class="px-4 py-3 border-b border-primary-100 flex items-center gap-3">
                <a href="{{ route('admin.support.index') }}" class="lg:hidden text-sm text-primary">&larr;</a>
                <div>
                    <div class="font-display font-semibold text-primary">{{ $active->name }}</div>
                    <div class="text-xs text-gray-500">{{ $active->email }}{{ $active->phone ? ' · '.$active->phone : '' }}</div>
                </div>
            </div>

            <div id="chat-scroll" class="flex-1 overflow-y-auto p-4 space-y-3 max-h-[60vh] bg-secondary/40">
                @foreach ($messages as $m)
                    <div class="flex {{ $m->from_customer ? 'justify-start' : 'justify-end' }}">
                        <div class="max-w-[75%] rounded-2xl px-4 py-2 text-sm whitespace-pre-line
                                    {{ $m->from_customer ? 'bg-white border border-primary-100 text-gray-800' : 'bg-primary text-white' }}">
                            {{ $m->body }}
                            <div class="mt-1 text-[10px] {{ $m->from_customer ? 'text-gray-400' : 'text-white/60' }}">
                                @unless ($m->from_customer){{ $m->sender?->name }} · @endunless{{ $m->created_at->format('d/m H:i') }}
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <form method="POST" action="{{ route('admin.support.reply', $active) }}" class="p-3 border-t border-primary-100 flex gap-2">
                @csrf
                <textarea name="body" rows="2" required maxlength="2000" placeholder="Tulis balasan..."
                          class="flex-1 rounded-lg border border-primary-100 px-3 py-2 text-sm resize-none"></textarea>
                <button class="btn-action btn-add btn-action-lg self-end">Kirim</button>
            </form>
            <script>
                (function () {
                    const el = document.getElementById('chat-scroll');
                    if (el) el.scrollTop = el.scrollHeight;
                    // Reload ringan tiap 15 detik agar pesan baru customer muncul, kecuali sedang mengetik.
                    const ta = document.querySelector('textarea[name="body"]');
                    setInterval(() => { if (!ta || ta.value.trim() === '') location.reload(); }, 15000);
                })();
            </script>
        @else
            <div class="flex-1 flex items-center justify-center text-sm text-gray-500 p-10">
                Pilih percakapan di sebelah kiri
            </div>
        @endif
    </div>
</div>
@endsection
