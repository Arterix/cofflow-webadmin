@php
    $navItems = [
        ['section' => 'Utama', 'route' => 'admin.dashboard',         'match' => 'admin.dashboard',    'label' => 'Dashboard',     'icon' => 'dashboard'],
        ['section' => 'Katalog & Stok', 'route' => 'admin.menus.index',       'match' => 'admin.menus.*',      'label' => 'Menu',          'icon' => 'restaurant_menu'],
        ['section' => 'Katalog & Stok', 'route' => 'admin.categories.index',  'match' => 'admin.categories.*', 'label' => 'Kategori',      'icon' => 'category'],
        ['section' => 'Katalog & Stok', 'route' => 'admin.condiments.index',  'match' => 'admin.condiments.*', 'label' => 'Opsi Tambahan',     'icon' => 'tune'],
        ['section' => 'Katalog & Stok', 'route' => 'admin.ingredients.index', 'match' => 'admin.ingredients.*','label' => 'Stok',          'icon' => 'inventory_2'],
        ['section' => 'Katalog & Stok', 'route' => 'admin.opnames.index',     'match' => 'admin.opnames.*',    'label' => 'Opname Stok',   'icon' => 'fact_check'],
        ['section' => 'Pelanggan & Penjualan', 'route' => 'admin.discounts.index',   'match' => 'admin.discounts.*',  'label' => 'Diskon',        'icon' => 'local_offer'],
        ['section' => 'Pelanggan & Penjualan', 'route' => 'admin.membership.index',  'match' => 'admin.membership.*', 'label' => 'Membership',    'icon' => 'loyalty'],
        ['section' => 'Pelanggan & Penjualan', 'route' => 'admin.orders.index',      'match' => 'admin.orders.*',     'label' => 'Order Monitor', 'icon' => 'receipt_long'],
        ['section' => 'Sistem', 'route' => 'admin.staff.index',       'match' => 'admin.staff.*',      'label' => 'Pegawai',       'icon' => 'groups'],
        ['section' => 'Sistem', 'route' => 'admin.report.index',      'match' => 'admin.report.*',     'label' => 'Laporan',       'icon' => 'assessment'],
        ['section' => 'Sistem', 'route' => 'admin.support.index',     'match' => 'admin.support.*',    'label' => 'Chat CS',       'icon' => 'support_agent'],
    ];
@endphp

<aside id="sidebar"
       class="fixed md:static inset-y-0 left-0 z-40 w-64 bg-primary text-white shadow-xl
              flex flex-col -translate-x-full md:translate-x-0 transition-transform duration-200 ease-out">
    <div class="px-6 py-5 border-b border-white/10 flex items-center justify-between">
        <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2.5">
            <img src="{{ asset('images/logo-konco-space-white.webp') }}"
                 alt="Konco Space"
                 class="h-8 max-w-[170px] object-contain"
                 onerror="this.style.display='none'; this.nextElementSibling.classList.remove('hidden'); this.nextElementSibling.classList.add('flex');" />
            <div class="hidden items-center gap-2">
                <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-accent text-primary font-display font-bold">K</span>
                <div>
                    <div class="font-display font-semibold text-base tracking-wide leading-none">Konco Space</div>
                    <p class="text-[10px] opacity-70 mt-1">Admin Dashboard</p>
                </div>
            </div>
        </a>
        <button type="button" onclick="window.closeSidebar()" class="md:hidden p-1 -mr-1 text-white/80 hover:text-white" aria-label="Tutup menu">
            <x-icon name="close" class="h-6 w-6" />
        </button>
    </div>

    <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
        @php $currentSection = null; @endphp
        @foreach ($navItems as $item)
            @if ($currentSection !== $item['section'])
                @php $currentSection = $item['section']; @endphp
                <p class="px-4 pt-4 pb-1 text-[10px] font-semibold uppercase tracking-wider text-white/50">{{ $currentSection }}</p>
            @endif
            @php $isActive = request()->routeIs($item['match']); @endphp
            <a href="{{ route($item['route']) }}"
               @if ($isActive) aria-current="page" @endif
               onclick="if(window.innerWidth<768)window.closeSidebar&&window.closeSidebar()"
               class="nav-link {{ $isActive ? 'active' : '' }} text-white/90">
                <x-icon :name="$item['icon']" class="h-5 w-5 shrink-0" />
                <span>{{ $item['label'] }}</span>
            </a>
        @endforeach
    </nav>

    <form method="POST" action="{{ route('admin.logout') }}" class="px-3 pb-4">
        @csrf
        <button type="submit" class="w-full nav-link text-white/80 hover:text-white">
            <x-icon name="logout" class="h-5 w-5" />
            Keluar
        </button>
    </form>
</aside>
