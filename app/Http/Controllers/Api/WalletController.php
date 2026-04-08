<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CoinPackage;
use App\Models\CoinTransaction;
use App\Models\WithdrawalRequest;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function __construct(private readonly WalletService $walletService)
    {
    }

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
        return response()->json(
            CoinPackage::active()->orderBy('coin_amount')->get()
        );
    }

    public function purchaseCoins(Request $request): JsonResponse
    {
        $data = $request->validate([
            'package_id'    => ['required', 'exists:coin_packages,id'],
            'store_receipt' => ['required', 'string'],
            'store'         => ['required', 'in:apple,google'],
        ]);

        $result = $this->walletService->processCoinPurchase(
            user:      auth()->user(),
            packageId: $data['package_id'],
            receipt:   $data['store_receipt'],
            store:     $data['store'],
        );

        if (! $result['success']) {
            return response()->json(['message' => $result['message']], 422);
        }

        return response()->json($result);
    }

    public function requestWithdrawal(Request $request): JsonResponse
    {
        $data = $request->validate([
            'diamond_amount'  => ['required', 'integer', 'min:' . config('wallet.min_withdrawal_diamonds', 1000)],
            'payment_method'  => ['required', 'in:paypal,bank_transfer,crypto'],
            'payment_details' => ['required', 'array'],
        ]);

        $user = auth()->user();

        if ($user->diamond_balance < $data['diamond_amount']) {
            return response()->json(['message' => 'Insufficient diamonds.'], 422);
        }

        $withdrawal = $this->walletService->createWithdrawalRequest($user, $data);

        return response()->json([
            'message'    => 'Withdrawal request submitted.',
            'request_id' => $withdrawal->id,
        ], 201);
    }

    public function transactions(): JsonResponse
    {
        $transactions = CoinTransaction::where('user_id', auth()->id())
            ->orderByDesc('created_at')
            ->paginate(30);

        return response()->json($transactions);
    }

    public function withdrawalHistory(): JsonResponse
    {
        $withdrawals = WithdrawalRequest::where('user_id', auth()->id())
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($withdrawals);
    }
}
