<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureOwner
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();
        if (! $user || ! $user->is_active || ! $user->isOwner()) {
            return redirect()->route('admin.login')->withErrors(['email' => 'Akses owner diperlukan']);
        }
        return $next($request);
    }
}
