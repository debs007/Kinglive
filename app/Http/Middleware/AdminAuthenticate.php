<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminAuthenticate
{
    public function handle($request, Closure $next)
        {
            // 👇 ONLY apply to admin routes
            if (! $request->is('admin') && ! $request->is('admin/*')) {
                return $next($request);
            }
        
            if (! auth()->guard('admin')->check()) {
                return redirect()->route('admin.login');
            }
        
            return $next($request);
        }
}
