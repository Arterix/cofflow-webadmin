@extends('admin.layouts.guest')

@section('title', 'Login Admin � Konco Space')

@section('content')
<div class="w-full max-w-md">
    <div class="text-center mb-6">
        <div class="flex justify-center mb-3">
            <img src="{{ asset('images/logo-konco-space.webp') }}"
                 alt="Konco Space"
                 class="h-24 w-auto object-contain"
                 onerror="this.onerror=null; this.src='{{ asset('images/logo-konco-space.png') }}';" />
        </div>
        <p class="text-sm text-gray-500">Masuk untuk mengelola coffee shop kamu</p>
    </div>

    <div class="bg-white rounded-2xl shadow-lg p-6 sm:p-8">
        @if ($errors->any())
            <div class="mb-4 rounded-lg bg-alert-50 border-l-4 border-alert text-primary-700 px-4 py-3 text-sm">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('admin.login.attempt') }}" class="space-y-4">
            @csrf

            <div>
                <label for="login-email" class="block text-sm font-medium text-primary mb-1">Email</label>
                <input id="login-email" type="email" name="email" value="{{ old('email') }}" required autofocus
                       autocomplete="email" inputmode="email"
                       class="w-full rounded-lg border border-primary-100 bg-white px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30" />
            </div>

            <div>
                <label for="login-password" class="block text-sm font-medium text-primary mb-1">Password</label>
                <div class="relative">
                    <input id="login-password" type="password" name="password" required
                           autocomplete="current-password"
                           class="w-full rounded-lg border border-primary-100 bg-white px-3 py-2.5 pr-10 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30" />
                    <button type="button"
                            onclick="(function(b){var i=document.getElementById('login-password');var on=i.type==='password';i.type=on?'text':'password';b.setAttribute('aria-pressed',on);b.querySelector('.eye-on').classList.toggle('hidden',!on);b.querySelector('.eye-off').classList.toggle('hidden',on);})(this)"
                            aria-label="Tampilkan password" aria-pressed="false"
                            class="absolute inset-y-0 right-0 px-3 flex items-center text-gray-500 hover:text-primary focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/30 rounded-r-lg">
                        <x-icon name="visibility_off" class="eye-on h-5 w-5 hidden" />
                        <x-icon name="visibility" class="eye-off h-5 w-5" />
                    </button>
                </div>
            </div>

            <label class="flex items-center gap-2 text-sm text-gray-600">
                <input type="checkbox" name="remember" class="rounded border-primary-100" />
                Ingat saya
            </label>

            <button type="submit"
                    class="w-full bg-primary text-white font-display font-medium rounded-lg px-4 py-2.5 hover:bg-primary-700 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40 focus-visible:ring-offset-2">
                Masuk
            </button>
        </form>

        @if (app()->environment('local'))
            <p class="mt-5 text-xs text-gray-500 text-center">
                Akun seed: <span class="font-mono">admin@cofflow.test</span> / <span class="font-mono">password</span>
            </p>
        @endif
    </div>
</div>
@endsection
