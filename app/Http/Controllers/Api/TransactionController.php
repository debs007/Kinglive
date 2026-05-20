<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CoinTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $type = $request->input('type', 'recharge');
        $page = (int) $request->input('page', 1);

        // Map frontend tab names to actual DB type values
        $typeMap = [
            'recharge' => ['recharge', 'purchase', 'coin_purchase', 'top_up', 'admin_adjustment'],
            'gift'     => ['gift', 'gift_sent', 'gift_received'],
            'game'     => ['game', 'game_win', 'game_loss', 'game_bet', 'game_reward'],
        ];

        $types = $typeMap[$type] ?? [$type];

        $txns = CoinTransaction::where('user_id', auth()->id())
            ->whereIn('type', $types)
            ->orderByDesc('created_at')
            ->paginate(20, ['*'], 'page', $page);

        return response()->json([
            'data' => $txns->map(fn ($t) => [
                'id'           => $t->id,
                'type'         => $t->type,
                'amount'       => (int) $t->amount,
                'balance_after'=> (int) ($t->balance_after ?? 0),
                'reference'    => $t->reference ?? '',
                'meta'         => $t->meta ?? null,
                'created_at'   => $t->created_at?->toIso8601String(),
            ]),
            'has_more'    => $txns->hasMorePages(),
            'total'       => $txns->total(),
            'current_page'=> $txns->currentPage(),
        ]);
    }
}