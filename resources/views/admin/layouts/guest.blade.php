<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>@yield('title', 'Konco Space')</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-dvh bg-secondary font-sans flex items-center justify-center px-4 pb-[env(safe-area-inset-bottom)]">
    @yield('content')
</body>
</html>
