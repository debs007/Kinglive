<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CoinTransaction;
use App\Models\CoinPackage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function balance(): JsonResponse
    {
        $user = auth()->user();
        return response()->json([
            'coin_balance'    => $user->coin_balance,
            'diamond_balance' => $user->diamond_balance,
        ]);
    }

    public function packages(): JsonResponse
    {
        $packages = CoinPackage::where('is_active', true)
            ->orderBy('coins')
            ->get();
        return response()->json(['packages' => $packages]);
    }

    public function purchaseCoins(Request $request): JsonResponse
    {
        // handled by coin seller portal — placeholder
        return response()->json(['message' => 'Contact a coin seller to purchase coins.'], 422);
    }

    public function requestWithdrawal(Request $request): JsonResponse
    {
        return response()->json(['message' => 'Withdrawal requests are processed manually.'], 422);
    }

    public function transactions(Request $request): JsonResponse
    {
        $txns = CoinTransaction::where('user_id', auth()->id())
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json([
            'data'     => $txns->items(),
            'has_more' => $txns->hasMorePages(),
        ]);
    }

    public function withdrawalHistory(): JsonResponse
    {
        return response()->json(['withdrawals' => []]);
    }
}
