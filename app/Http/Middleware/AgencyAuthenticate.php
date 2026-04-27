<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AgencyAuthenticate
{
    public function handle(Request $request, Closure $next): Response
    {
        $id    = $request->session()->get('agency_id');
        $token = $request->session()->get('agency_token');

        if (! $id || ! $token) {
            return redirect()->route('agency.login');
        }

        $agency = \App\Models\Agency::find($id);

        if (! $agency || ! $agency->is_active || $agency->session_token !== $token) {
            $request->session()->invalidate();
            return redirect()->route('agency.login');
        }

        return $next($request);
    }
}
