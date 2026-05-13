<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Unauthenticated',
            ], 401);
        }

        if (! $user->is_active) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Akun kamu dinonaktifkan',
            ], 403);
        }

        if (! in_array($user->role, $roles, true)) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Forbidden',
            ], 403);
        }

        return $next($request);
    }
}
