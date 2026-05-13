@php
    $navItems = [
        ['route' => 'admin.dashboard',         'match' => 'admin.dashboard',    'label' => 'Dashboard',     'icon' => 'M3 12l2-2 7-7 7 7 2 2v9a2 2 0 01-2 2h-4v-6h-6v6H5a2 2 0 01-2-2v-9z'],
        ['route' => 'admin.menus.index',       'match' => 'admin.menus.*',      'label' => 'Menu',          'icon' => 'M4 6h16M4 12h16M4 18h7'],
        ['route' => 'admin.categories.index',  'match' => 'admin.categories.*', 'label' => 'Kategori',      'icon' => 'M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h6v6h-6z'],
        ['route' => 'admin.condiments.index',  'match' => 'admin.condiments.*', 'label' => 'Condiment',     'icon' => 'M12 2v6m0 8v6M2 12h6m8 0h6M5 5l4 4m6 6l4 4M5 19l4-4m6-6l4-4'],
        ['route' => 'admin.ingredients.index', 'match' => 'admin.ingredients.*','label' => 'Stok',          'icon' => 'M3 7l9-4 9 4-9 4-9-4zm0 6l9 4 9-4M3 17l9 4 9-4'],
        ['route' => 'admin.opnames.index',     'match' => 'admin.opnames.*',    'label' => 'Opname Stok',   'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 8l2 2 4-4'],
        ['route' => 'admin.discounts.index',   'match' => 'admin.discounts.*',  'label' => 'Diskon',        'icon' => 'M9 14l6-6M9 9h.01M15 15h.01M21 12c0 4.97-4.03 9-9 9S3 16.97 3 12 7.03 3 12 3s9 4.03 9 9z'],
        ['route' => 'admin.orders.index',      'match' => 'admin.orders.*',     'label' => 'Order Monitor', 'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2'],
        ['route' => 'admin.staff.index',       'match' => 'admin.staff.*',      'label' => 'Pegawai',       'icon' => 'M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1.13a4 4 0 100-8 4 4 0 000 8zM21 12a4 4 0 10-8 0 4 4 0 008 0z'],
        ['route' => 'admin.report.index',      'match' => 'admin.report.*',     'label' => 'Laporan',       'icon' => 'M9 17v-6h6v6m-3 4h6a2 2 0 002-2V7l-5-5H5a2 2 0 00-2 2v14a2 2 0 002 2h7'],
    ];
@endphp

<aside id="sidebar"
       class="fixed md:static inset-y-0 left-0 z-40 w-64 bg-primary text-white shadow-xl
              flex flex-col -translate-x-full md:translate-x-0 transition-transform duration-200 ease-out">
    <div class="px-6 py-5 border-b border-white/10 flex items-center justify-between">
        <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2">
            <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-accent text-primary font-display font-bold">C</span>
            <div>
                <div class="font-display font-semibold text-lg tracking-wide leading-none">Cofflow</div>
                <p class="text-xs opacity-70 mt-1">Admin Dashboard</p>
            </div>
        </a>
        <button type="button" onclick="window.closeSidebar()" class="md:hidden p-1 -mr-1 text-white/80 hover:text-white" aria-label="Tutup menu">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6L6 18"/></svg>
        </button>
    </div>

    <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
        @foreach ($navItems as $item)
            @php $isActive = request()->routeIs($item['match']); @endphp
            <a href="{{ route($item['route']) }}"
               @if ($isActive) aria-current="page" @endif
               onclick="if(window.innerWidth<768)window.closeSidebar&&window.closeSidebar()"
               class="nav-link {{ $isActive ? 'active' : '' }} text-white/90">
                <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icon'] }}"></path>
                </svg>
                <span>{{ $item['label'] }}</span>
            </a>
        @endforeach
    </nav>

    <form method="POST" action="{{ route('admin.logout') }}" class="px-3 pb-4">
        @csrf
        <button type="submit" class="w-full nav-link text-white/80 hover:text-white">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H5a3 3 0 01-3-3V7a3 3 0 013-3h5a3 3 0 013 3v1"/>
            </svg>
            Keluar
        </button>
    </form>
</aside>
