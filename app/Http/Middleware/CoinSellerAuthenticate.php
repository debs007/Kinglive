<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CoinSellerAuthenticate
{
    public function handle(Request $request, Closure $next): Response
    {
        // Use request session directly — more reliable with Octane/Swoole
        $id    = $request->session()->get('coin_seller_id');
        $token = $request->session()->get('coin_seller_token');

        if (! $id || ! $token) {
            return redirect()->route('coin_seller.login');
        }

        $seller = \App\Models\CoinSeller::find($id);

        if (! $seller || ! $seller->is_active || $seller->session_token !== $token) {
            $request->session()->invalidate();
            return redirect()->route('coin_seller.login');
        }

        return $next($request);
    }
}
