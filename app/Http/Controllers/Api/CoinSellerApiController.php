<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CoinSeller;
use Illuminate\Http\JsonResponse;

class CoinSellerApiController extends Controller
{
    /**
     * List active coin sellers sorted by price_per_100k low to high.
     * Called from Flutter wallet/buy coins screen.
     */
    public function index(): JsonResponse
    {
        $sellers = CoinSeller::where('is_active', true)
            ->whereNotNull('price_per_100k')
            ->whereNotNull('whatsapp_number')
            ->where('coin_balance', '>', 0)
            ->orderBy('price_per_100k', 'asc')
            ->get(['id', 'name', 'price_per_100k', 'whatsapp_number', 'coin_balance']);

        return response()->json([
            'sellers' => $sellers->map(fn ($s) => [
                'id'              => $s->id,
                'name'            => $s->name,
                'price_per_100k'  => (float) $s->price_per_100k,
                'whatsapp_number' => $s->whatsapp_number,
                'coin_balance'    => $s->coin_balance,
            ]),
        ]);
    }
}
