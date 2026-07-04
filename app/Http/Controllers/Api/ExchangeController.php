<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CoinTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExchangeController extends Controller
{
    // Rate: 100 diamonds = 82 coins
    // Based on: 100k coins = 1100 taka, 100k diamonds = 900 taka
    // 1 diamond = (900/100000) / (1100/100000) = 0.8182 coins
    private const DIAMONDS_PER_BATCH = 100;
    private const COINS_PER_BATCH    = 60;
    private const MIN_EXCHANGE       = 50000; // minimum 50,000 diamonds to exchange

    public function rate(): JsonResponse
    {
        return response()->json([
            'diamonds_per_batch' => self::DIAMONDS_PER_BATCH,
            'coins_per_batch'    => self::COINS_PER_BATCH,
            'min_diamonds'       => self::MIN_EXCHANGE, // 50,000
            'rate_label'         => '100 💎 = 60 🪙',
            'note'               => 'Exchange rate based on: 100K coins = ৳1100, 100K diamonds = ৳600',
        ]);
    }

    public function exchange(Request $request): JsonResponse
    {
        if (\App\Models\Setting::get('exchange_enabled', '1') === '0') {
            return response()->json([
                'message' => 'Diamond exchange is currently disabled.',
                'code'    => 'exchange_disabled',
            ], 403);
        }

        $request->validate([
            'diamonds' => ['required', 'integer', 'min:' . self::MIN_EXCHANGE], // 50,000
        ]);

        $user     = auth()->user();
        $diamonds = (int) $request->diamonds;

        // Must be multiple of 100
        if ($diamonds % self::DIAMONDS_PER_BATCH !== 0) {
            return response()->json([
                'message' => 'Amount must be a multiple of ' . self::DIAMONDS_PER_BATCH . ' diamonds.',
                'code'    => 'invalid_amount',
            ], 422);
        }

        // Check balance
        if ($user->diamond_balance < $diamonds) {
            return response()->json([
                'message' => 'Insufficient diamond balance.',
                'code'    => 'insufficient_diamonds',
            ], 400);
        }

        $batches  = $diamonds / self::DIAMONDS_PER_BATCH;
        $coins    = $batches * self::COINS_PER_BATCH;

        DB::transaction(function () use ($user, $diamonds, $coins) {
            // Deduct diamonds
            $user->decrement('diamond_balance', $diamonds);

            CoinTransaction::create([
                'user_id'       => $user->id,
                'type'          => 'exchange',
                'amount'        => -$diamonds,
                'balance_after' => $user->fresh()->diamond_balance,
                'reference'     => "exchange:diamonds:{$diamonds}:coins:{$coins}",
            ]);

            // Credit coins
            $user->increment('coin_balance', $coins);

            CoinTransaction::create([
                'user_id'       => $user->id,
                'type'          => 'exchange',
                'amount'        => $coins,
                'balance_after' => $user->fresh()->coin_balance,
                'reference'     => "exchange:coins:{$coins}:from_diamonds:{$diamonds}",
            ]);
        });

        $fresh = $user->fresh();

        return response()->json([
            'message'          => "Exchanged {$diamonds} 💎 → {$coins} 🪙 successfully!",
            'diamonds_spent'   => $diamonds,
            'coins_received'   => $coins,
            'diamond_balance'  => $fresh->diamond_balance,
            'coin_balance'     => $fresh->coin_balance,
        ]);
    }
}
