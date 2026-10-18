@extends('admin.layouts.app')

@section('title', 'Membership · Konco Space Admin')
@section('page_title', 'Membership & Poin')
@section('page_sub', 'Saldo poin member, tier, dan aturan perolehan poin')

@php
    $input = 'w-full rounded-lg border border-primary-100 px-3 py-2 text-sm';
    $tierPill = ['bronze' => 'is-warning', 'silver' => 'is-muted', 'gold' => 'is-strong'];
    $cards = [
        ['label' => 'Total Member', 'value' => number_format($stats['members'], 0, ',', '.'), 'icon' => 'groups'],
        ['label' => 'Poin Beredar', 'value' => number_format($stats['outstanding'], 0, ',', '.'), 'icon' => 'loyalty'],
        ['label' => 'Poin Tertunda', 'value' => number_format($stats['pending'], 0, ',', '.'), 'icon' => 'schedule'],
        ['label' => 'Poin Diberikan', 'value' => number_format($stats['earned'], 0, ',', '.'), 'icon' => 'flash_on'],
        ['label' => 'Poin Ditukar', 'value' => number_format($stats['redeemed'], 0, ',', '.'), 'icon' => 'local_offer'],
    ];
    $typeLabel = ['earn' => ['Dapat', 'is-success'], 'redeem' => ['Tukar', 'is-warning'], 'adjust' => ['Penyesuaian', 'is-muted']];
@endphp

@section('content')
<div class="space-y-5">
    @unless ($settings->is_enabled)
        <div class="rounded-xl border border-alert bg-alert-50 px-4 py-3 text-sm text-alert">
            Program poin sedang <b>nonaktif</b>. Customer tidak mendapat poin dan tidak bisa menukar reward.
        </div>
    @endunless

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
        @foreach ($cards as $c)
            <div class="rounded-2xl border-l-4 bg-white border-primary-100 p-5 shadow-sm">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-xs uppercase tracking-wide text-gray-500 font-medium">{{ $c['label'] }}</p>
                        <p class="mt-1 font-display text-2xl font-semibold text-primary">{{ $c['value'] }}</p>
                    </div>
                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-primary text-white">
                        <x-icon :name="$c['icon']" class="h-5 w-5" />
                    </span>
                </div>
            </div>
        @endforeach
    </div>

    <div class="flex gap-2 border-b border-primary-100 overflow-x-auto -mx-1 px-1">
        @foreach (['members' => 'Member', 'history' => 'Riwayat Poin', 'settings' => 'Pengaturan Poin'] as $k => $label)
            <a href="{{ route('admin.membership.index', ['tab' => $k]) }}"
               class="px-4 py-2 text-sm font-medium border-b-2 -mb-px whitespace-nowrap {{ $tab === $k ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:text-primary' }}">
                {{ $label }}
            </a>
        @endforeach
        <a href="{{ route('admin.discounts.index', ['tab' => 'reward']) }}" class="px-4 py-2 text-sm font-medium border-b-2 border-transparent -mb-px whitespace-nowrap text-gray-500 hover:text-primary">
            Katalog Reward →
        </a>
    </div>

    {{-- ============ MEMBERS ============ --}}
    @if ($tab === 'members')
        <form method="GET" class="flex gap-2 max-w-md">
            <input type="hidden" name="tab" value="members" />
            <input name="q" value="{{ request('q') }}" placeholder="Cari nama / email" class="{{ $input }}" />
            <button class="btn-action btn-info btn-action-lg">Cari</button>
        </form>

        <div class="bg-white rounded-2xl border border-primary-100 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
            <table class="w-full text-sm min-w-[760px]">
                <thead class="bg-primary-50 text-xs uppercase text-primary">
                    <tr>
                        <th class="text-left px-4 py-3">Member</th>
                        <th class="text-center px-4 py-3">Tier</th>
                        <th class="text-right px-4 py-3">Saldo Poin</th>
                        <th class="text-right px-4 py-3">Poin Tertunda</th>
                        <th class="text-right px-4 py-3">Total Poin Didapat</th>
                        <th class="text-left px-4 py-3">Bergabung</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-primary-100">
                    @forelse ($members as $m)
                        <tr>
                            <td class="px-4 py-3">
                                <div class="font-medium text-primary">{{ $m->name }}</div>
                                <div class="text-xs text-gray-500">{{ $m->email }}</div>
                            </td>
                            <td class="px-4 py-3 text-center"><span class="status-pill {{ $tierPill[$m->tier] ?? '' }} capitalize">{{ $m->tier }}</span></td>
                            <td class="px-4 py-3 text-right font-semibold">{{ number_format($m->points_balance, 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right text-gray-500">{{ (int) $m->pending_points > 0 ? '+'.number_format((int) $m->pending_points, 0, ',', '.') : '-' }}</td>
                            <td class="px-4 py-3 text-right">{{ number_format($m->lifetime_points, 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-xs text-gray-500">{{ $m->created_at?->format('d/m/Y') }}</td>
                            <td class="px-4 py-3 text-right">
                                <details class="js-popover inline-block text-left relative">
                                    <summary class="btn-action btn-edit cursor-pointer list-none">Sesuaikan Poin</summary>
                                    <form method="POST" action="{{ route('admin.membership.adjust', $m) }}" class="absolute right-0 mt-1 z-10 bg-white border border-primary-100 rounded-lg p-3 w-64 space-y-2 shadow-lg">
                                        @csrf
                                        <input name="points" type="number" required placeholder="+50 atau -20" class="w-full rounded-md border border-primary-100 px-2 py-1 text-sm" />
                                        <input name="reason" required maxlength="200" placeholder="Alasan (wajib)" class="w-full rounded-md border border-primary-100 px-2 py-1 text-sm" />
                                        <p class="text-[11px] text-gray-500">Penyesuaian hanya mengubah saldo, tidak memengaruhi tier.</p>
                                        <button class="btn-action btn-edit btn-action-block">Simpan</button>
                                    </form>
                                </details>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-10 text-center text-sm text-gray-500">Belum ada member</td></tr>
                    @endforelse
                </tbody>
            </table>
            </div>
        </div>

    {{-- ============ HISTORY ============ --}}
    @elseif ($tab === 'history')
        <div class="bg-white rounded-2xl border border-primary-100 shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b border-primary-100">
                <h3 class="font-display font-semibold text-primary">Poin Tertunda</h3>
                <p class="text-xs text-gray-500">Pesanan dari aplikasi yang belum selesai. Poin masuk saldo saat status <b>selesai</b>, batal otomatis jika pesanan dibatalkan.</p>
            </div>
            <div class="overflow-x-auto">
            <table class="w-full text-sm min-w-[640px]">
                <thead class="bg-primary-50 text-xs uppercase text-primary">
                    <tr>
                        <th class="text-left px-4 py-3">Waktu Pesan</th>
                        <th class="text-left px-4 py-3">Member</th>
                        <th class="text-left px-4 py-3">Pesanan</th>
                        <th class="text-center px-4 py-3">Status</th>
                        <th class="text-right px-4 py-3">Poin</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-primary-100">
                    @forelse ($pendingOrders as $o)
                        <tr>
                            <td class="px-4 py-3 text-xs text-gray-500 whitespace-nowrap">{{ $o->created_at->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-3 font-medium text-primary">{{ $o->user?->name ?? '-' }}</td>
                            <td class="px-4 py-3"><a href="{{ route('admin.orders.show', $o->id) }}" class="text-primary underline">#{{ $o->id }}</a></td>
                            <td class="px-4 py-3 text-center"><span class="status-pill is-muted">Menunggu selesai</span></td>
                            <td class="px-4 py-3 text-right font-semibold text-gray-500">+{{ number_format((int) $o->points_base, 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-6 text-center text-sm text-gray-500">Tidak ada poin tertunda</td></tr>
                    @endforelse
                </tbody>
            </table>
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-primary-100 shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b border-primary-100">
                <h3 class="font-display font-semibold text-primary">Riwayat Transaksi Poin</h3>
            </div>
            <div class="overflow-x-auto">
            <table class="w-full text-sm min-w-[760px]">
                <thead class="bg-primary-50 text-xs uppercase text-primary">
                    <tr>
                        <th class="text-left px-4 py-3">Waktu</th>
                        <th class="text-left px-4 py-3">Member</th>
                        <th class="text-center px-4 py-3">Jenis</th>
                        <th class="text-left px-4 py-3">Keterangan</th>
                        <th class="text-right px-4 py-3">Poin</th>
                        <th class="text-right px-4 py-3">Saldo</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-primary-100">
                    @forelse ($transactions as $t)
                        @php [$tl, $tc] = $typeLabel[$t->type] ?? [$t->type, '']; @endphp
                        <tr>
                            <td class="px-4 py-3 text-xs text-gray-500 whitespace-nowrap">{{ $t->created_at->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-3 font-medium text-primary">{{ $t->user?->name ?? '-' }}</td>
                            <td class="px-4 py-3 text-center"><span class="status-pill {{ $tc }}">{{ $tl }}</span></td>
                            <td class="px-4 py-3 text-gray-600">
                                {{ $t->description }}
                                @if ($t->order_id)
                                    · <a href="{{ route('admin.orders.show', $t->order_id) }}" class="text-primary underline">lihat order</a>
                                @endif
                                @if ($t->creator)<span class="text-xs text-gray-400">(oleh {{ $t->creator->name }})</span>@endif
                            </td>
                            <td class="px-4 py-3 text-right font-semibold {{ $t->points >= 0 ? 'text-green-700' : 'text-alert' }}">{{ $t->points > 0 ? '+' : '' }}{{ number_format($t->points, 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right">{{ number_format($t->balance_after, 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-10 text-center text-sm text-gray-500">Belum ada transaksi poin</td></tr>
                    @endforelse
                </tbody>
            </table>
            </div>
        </div>

    {{-- ============ SETTINGS ============ --}}
    @else
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
            <form method="POST" action="{{ route('admin.membership.settings.update') }}" class="lg:col-span-2 bg-white rounded-2xl border border-primary-100 p-6 shadow-sm space-y-5">
                @csrf @method('PUT')

                <label class="flex items-center gap-2 text-sm font-medium text-primary">
                    <input type="checkbox" name="is_enabled" value="1" @checked($settings->is_enabled) />
                    Program poin aktif
                </label>

                <div>
                    <h3 class="font-display font-semibold text-primary mb-2">Perolehan Poin</h3>
                    <label class="block text-sm text-gray-600 mb-1">Harga minimal produk agar dapat 1 poin (Rp)</label>
                    <input name="min_item_price" type="number" min="0" step="500" value="{{ old('min_item_price', $settings->min_item_price) }}" required class="{{ $input }} max-w-xs" />
                    <p class="text-xs text-gray-500 mt-1">Setiap 1 produk dengan harga menu ≥ nilai ini mendapat 1 poin. Menu yang punya "poin per item" sendiri (atur di form menu) memakai nilai tersebut.</p>
                </div>

                <div>
                    <h3 class="font-display font-semibold text-primary mb-2">Tier Membership</h3>
                    <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="text-xs uppercase text-gray-500">
                            <tr><th class="text-left py-2">Tier</th><th class="text-left py-2">Minimal total poin didapat</th></tr>
                        </thead>
                        <tbody class="divide-y divide-primary-100">
                            <tr>
                                <td class="py-2 font-medium">Bronze</td>
                                <td class="py-2 pr-3 text-gray-500">0 (semua member)</td>
                            </tr>
                            <tr>
                                <td class="py-2 font-medium">Silver</td>
                                <td class="py-2 pr-3"><input name="silver_threshold" type="number" min="1" value="{{ old('silver_threshold', $settings->silver_threshold) }}" required class="{{ $input }} max-w-[160px]" /></td>
                            </tr>
                            <tr>
                                <td class="py-2 font-medium">Gold</td>
                                <td class="py-2 pr-3"><input name="gold_threshold" type="number" min="2" value="{{ old('gold_threshold', $settings->gold_threshold) }}" required class="{{ $input }} max-w-[160px]" /></td>
                            </tr>
                        </tbody>
                    </table>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">Tier dihitung dari total poin yang pernah didapat, jadi menukar poin tidak menurunkan tier. Tier menentukan reward mana yang bisa ditukar.</p>
                </div>

                <div>
                    <h3 class="font-display font-semibold text-primary mb-2">Voucher</h3>
                    <label class="block text-sm text-gray-600 mb-1">Masa berlaku voucher setelah ditukar (hari, 0 = tanpa batas)</label>
                    <input name="voucher_valid_days" type="number" min="0" value="{{ old('voucher_valid_days', $settings->voucher_valid_days) }}" required class="{{ $input }} max-w-xs" />
                </div>

                <div class="flex justify-end">
                    <button class="btn-action btn-edit btn-action-lg">Simpan Pengaturan</button>
                </div>
            </form>

            <div class="bg-primary-50 rounded-2xl border border-primary-100 p-5 text-sm text-gray-700 space-y-3 h-fit">
                <h3 class="font-display font-semibold text-primary">Cara poin dihitung</h3>
                <ol class="list-decimal pl-4 space-y-1.5">
                    <li>Hanya pesanan dari <b>aplikasi customer</b> yang mendapat poin. Walk-in di kasir tidak.</li>
                    <li>Setiap 1 produk dengan harga menu ≥ Rp {{ number_format($settings->min_item_price, 0, ',', '.') }} = <b>1 poin</b>. Di bawah itu tidak dapat poin.</li>
                    <li>Jika menu punya <b>poin per item</b> sendiri, nilai itu yang dipakai.</li>
                    <li>Opsi tambahan, diskon produk/event, kode promo, dan voucher poin tidak mengubah jumlah poin.</li>
                    <li>Poin masuk saat pesanan berstatus <b>selesai</b> (completed).</li>
                </ol>
                <p class="text-xs text-gray-500">Contoh: 2 Latte Rp 27.000 + 1 Croissant Rp 15.000 + 1 Air Mineral Rp 5.000 → 2 + 1 + 0 = 3 poin.</p>
            </div>
        </div>
    @endif
</div>
@endsection
