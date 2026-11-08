<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * Only authenticated users whose role list contains at least one of the
     * required roles are allowed through. All others receive a 403 response.
     *
     * @param  string  ...$roles  One or more role names that are permitted.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }
            return redirect()->route('login');
        }

        $userRoles = $user->roles->pluck('name')->toArray();

        foreach ($roles as $role) {
            if (in_array($role, $userRoles, strict: true)) {
                return $next($request);
            }
        }

        return response()->json([
            'message' => 'Akses ditolak. Anda tidak memiliki izin untuk tindakan ini.',
        ], 403);
    }
}

