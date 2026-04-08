<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminAuthenticate
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! auth()->check()) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Unauthenticated.'], 401)
                : redirect()->route('admin.login');
        }

        if (! in_array(auth()->user()->role, ['admin', 'super_admin', 'moderator'])) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Forbidden.'], 403)
                : redirect()->route('admin.login')->withErrors(['Access denied.']);
        }

        return $next($request);
    }
}
