@extends('admin.layouts.guest')

@section('title', 'Login Admin · Cofflow')

@section('content')
<div class="w-full max-w-md">
    <div class="text-center mb-6">
        <span class="inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-primary text-secondary font-display font-bold text-2xl shadow-lg">C</span>
        <h1 class="mt-3 font-display font-semibold text-2xl text-primary">Cofflow Admin</h1>
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
                        <svg class="eye-on h-5 w-5 hidden" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18M10.6 10.6a2 2 0 002.8 2.8M9.9 5.1A10.5 10.5 0 0112 5c5 0 9 4 10 7-.5 1.4-1.6 3-3.2 4.4M6.2 6.2C4 7.7 2.5 9.8 2 12c1 3 5 7 10 7 1.5 0 2.9-.3 4.2-.9"/></svg>
                        <svg class="eye-off h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
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
