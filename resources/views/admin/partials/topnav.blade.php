<header class="bg-white border-b border-primary-100 shadow-sm sticky top-0 z-20">
    <div class="flex items-center justify-between px-4 sm:px-6 py-3 gap-3">
        <div class="flex items-center gap-3 min-w-0">
            <button type="button" onclick="window.toggleSidebar && window.toggleSidebar()"
                    class="md:hidden inline-flex h-9 w-9 items-center justify-center rounded-lg border border-primary-100 text-primary hover:bg-secondary"
                    aria-label="Buka menu">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
            <div class="min-w-0">
                <h1 class="font-display font-semibold text-primary text-base sm:text-lg truncate">@yield('page_title', 'Dashboard')</h1>
                <p class="text-xs text-gray-500 truncate">@yield('page_sub', 'Selamat datang kembali')</p>
            </div>
        </div>

        <div class="flex items-center gap-3 sm:gap-4 shrink-0">
            <span class="text-sm text-gray-500 hidden lg:block">{{ now()->format('d M Y, H:i') }}</span>
            <div class="flex items-center gap-2" aria-label="Akun pengguna">
                <span class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-primary text-white font-display font-semibold"
                      aria-hidden="true" title="{{ auth()->user()->name }}">
                    {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                </span>
                <span class="sr-only">Login sebagai {{ auth()->user()->name }} ({{ ucfirst(auth()->user()->role) }})</span>
                <div class="hidden sm:block text-right">
                    <div class="text-sm font-medium text-primary truncate max-w-[140px]">{{ auth()->user()->name }}</div>
                    <div class="text-xs text-gray-500">{{ ucfirst(auth()->user()->role) }}</div>
                </div>
            </div>
        </div>
    </div>
</header>
