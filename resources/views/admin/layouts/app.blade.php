<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <title>@yield('title', 'Cofflow Admin')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-secondary text-gray-800 font-sans">
    <a href="#main-content"
       class="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50 bg-primary text-white px-3 py-2 rounded-lg text-sm">
        Lompat ke konten utama
    </a>
    <div class="min-h-screen md:flex">
        @include('admin.partials.sidebar')

        {{-- Mobile backdrop --}}
        <div id="sidebar-backdrop"
             class="fixed inset-0 bg-black/40 z-30 hidden md:hidden"
             onclick="window.closeSidebar && window.closeSidebar()"></div>

        <div class="flex-1 flex flex-col min-w-0">
            @include('admin.partials.topnav')

            <main id="main-content" tabindex="-1" class="flex-1 p-4 sm:p-5 md:p-6 overflow-x-auto">
                @if (session('status'))
                    <div class="mb-4 rounded-lg bg-accent-50 border-l-4 border-accent text-primary-700 px-4 py-3">
                        {{ session('status') }}
                    </div>
                @endif
                @if ($errors->any() && ! request()->routeIs('admin.login'))
                    <div class="mb-4 rounded-lg bg-alert-50 border-l-4 border-alert text-primary-700 px-4 py-3">
                        <ul class="list-disc list-inside text-sm">
                            @foreach ($errors->all() as $err)
                                <li>{{ $err }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>

    <script>
        (function () {
            const sidebar = document.getElementById('sidebar');
            const backdrop = document.getElementById('sidebar-backdrop');
            if (!sidebar || !backdrop) return;

            window.openSidebar = function () {
                sidebar.classList.remove('-translate-x-full');
                backdrop.classList.remove('hidden');
                document.body.classList.add('overflow-hidden', 'md:overflow-auto');
            };
            window.closeSidebar = function () {
                sidebar.classList.add('-translate-x-full');
                backdrop.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
            };
            window.toggleSidebar = function () {
                sidebar.classList.contains('-translate-x-full') ? window.openSidebar() : window.closeSidebar();
            };

            // Close on viewport resize to md+
            const mq = window.matchMedia('(min-width: 768px)');
            mq.addEventListener('change', (e) => {
                if (e.matches) {
                    sidebar.classList.remove('-translate-x-full');
                    backdrop.classList.add('hidden');
                    document.body.classList.remove('overflow-hidden');
                } else {
                    sidebar.classList.add('-translate-x-full');
                }
            });

            // Close sidebar on Escape (mobile)
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && !sidebar.classList.contains('-translate-x-full') && window.innerWidth < 768) {
                    window.closeSidebar();
                }
            });
        })();

        // Close <details class="js-popover"> when clicking outside or pressing Escape.
        // Plain <details> (e.g. accordion groups) are untouched so multiple can stay open.
        document.addEventListener('click', (e) => {
            document.querySelectorAll('details.js-popover[open]').forEach((d) => {
                if (!d.contains(e.target)) d.removeAttribute('open');
            });
        });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                document.querySelectorAll('details.js-popover[open]').forEach((d) => d.removeAttribute('open'));
            }
        });
    </script>
</body>
</html>
