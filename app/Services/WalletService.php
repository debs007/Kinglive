<?php

namespace App\Services;

use App\Models\CoinPackage;
use App\Models\CoinTransaction;
use App\Models\User;
use App\Models\WithdrawalRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class WalletService
{
    public function processCoinPurchase(
        User   $user,
        int    $packageId,
        string $receipt,
        string $store
    ): array {
        $package = CoinPackage::findOrFail($packageId);

        if (app()->isProduction() && ! $this->verifyReceipt($receipt, $store, $package->store_product_id)) {
            return ['success' => false, 'message' => 'Receipt verification failed'];
        }

        $totalCoins = $package->coin_amount + $package->bonus_coins;

        DB::transaction(function () use ($user, $package, $totalCoins) {
            $user->increment('coin_balance', $totalCoins);

            CoinTransaction::create([
                'user_id'       => $user->id,
                'type'          => 'purchase',
                'amount'        => $totalCoins,
                'balance_after' => $user->fresh()->coin_balance,
                'reference'     => "pkg:{$package->id}",
                'meta'          => [
                    'package_name' => $package->name,
                    'price_usd'    => $package->price_usd,
                    'store'        => 'iap',
                ],
            ]);
        });

        return [
            'success'     => true,
            'coins_added' => $totalCoins,
            'new_balance' => $user->fresh()->coin_balance,
        ];
    }

    public function adminCreditCoins(User $user, int $amount, string $reason): void
    {
        $user->increment('coin_balance', $amount);

        CoinTransaction::create([
            'user_id'       => $user->id,
            'type'          => 'admin_credit',
            'amount'        => $amount,
            'balance_after' => $user->fresh()->coin_balance,
            'reference'     => $reason,
        ]);
    }

    public function createWithdrawalRequest(User $user, array $data): WithdrawalRequest
    {
        $usdRate = (float) config('wallet.diamond_to_usd_rate', 0.001);
        $usd     = $data['diamond_amount'] * $usdRate;

        return DB::transaction(function () use ($user, $data, $usd) {
            $user->decrement('diamond_balance', $data['diamond_amount']);

            CoinTransaction::create([
                'user_id'       => $user->id,
                'type'          => 'withdrawal',
                'amount'        => -$data['diamond_amount'],
                'balance_after' => $user->fresh()->diamond_balance,
            ]);

            return WithdrawalRequest::create([
                'user_id'         => $user->id,
                'diamond_amount'  => $data['diamond_amount'],
                'usd_amount'      => $usd,
                'payment_method'  => $data['payment_method'],
                'payment_details' => $data['payment_details'],
                'status'          => 'pending',
            ]);
        });
    }

    public function refundWithdrawal(WithdrawalRequest $withdrawal, string $adminNote): void
    {
        DB::transaction(function () use ($withdrawal, $adminNote) {
            $withdrawal->update([
                'status'     => 'rejected',
                'admin_note' => $adminNote,
            ]);

            User::where('id', $withdrawal->user_id)
                ->increment('diamond_balance', $withdrawal->diamond_amount);

            CoinTransaction::create([
                'user_id'       => $withdrawal->user_id,
                'type'          => 'admin_credit',
                'amount'        => $withdrawal->diamond_amount,
                'balance_after' => User::find($withdrawal->user_id)->diamond_balance,
                'reference'     => "withdrawal_rejected:{$withdrawal->id}",
            ]);
        });
    }

    // ── Private ───────────────────────────────────────────────────────────────

    private function verifyReceipt(string $receipt, string $store, string $productId): bool
    {
        if ($store === 'apple') {
            return $this->verifyAppleReceipt($receipt, $productId);
        }

        if ($store === 'google') {
            return $this->verifyGoogleReceipt($receipt, $productId);
        }

        return false;
    }

    private function verifyAppleReceipt(string $receipt, string $productId): bool
    {
        $response = Http::post('https://buy.itunes.apple.com/verifyReceipt', [
            'receipt-data' => $receipt,
            'password'     => config('services.apple.shared_secret'),
        ]);

        if ($response->json('status') === 21007) {
            // Sandbox receipt, try sandbox endpoint
            $response = Http::post('https://sandbox.itunes.apple.com/verifyReceipt', [
                'receipt-data' => $receipt,
                'password'     => config('services.apple.shared_secret'),
            ]);
        }

        return $response->json('status') === 0;
    }

    private function verifyGoogleReceipt(string $receipt, string $productId): bool
    {
        // Implement Google Play Developer API verification
        // Requires service account credentials
        return true;
    }
}
