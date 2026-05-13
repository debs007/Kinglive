<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GiftTransaction;
use App\Models\User;
use App\Services\LevelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SyncUserLevelsController extends Controller
{
    /**
     * Sync total_coins_sent and level for ALL users from historical gift transactions.
     * Called once from admin panel after this feature is deployed.
     * Can also be re-run safely at any time (idempotent).
     */
    public function sync(Request $request): JsonResponse
    {
        $updated = 0;

        // Get total lifetime coins sent per user from gift_transactions
        $totals = GiftTransaction::selectRaw('sender_id, SUM(coin_total) AS total_sent')
            ->groupBy('sender_id')
            ->get()
            ->keyBy('sender_id');

        // Process in chunks to avoid memory issues
        User::chunk(200, function ($users) use ($totals, &$updated) {
            foreach ($users as $user) {
                $totalSent = (int) ($totals->get($user->id)?->total_sent ?? 0);
                $level     = LevelService::calculate($totalSent);

                $user->update([
                    'total_coins_sent' => $totalSent,
                    'level'            => $level,
                ]);

                $updated++;
            }
        });

        return response()->json([
            'success' => true,
            'message' => "Synced {$updated} users.",
            'updated' => $updated,
        ]);
    }

    /**
     * Preview stats — how many users are at each level.
     */
    public function stats(): JsonResponse
    {
        $distribution = User::selectRaw('level, COUNT(*) as count')
            ->groupBy('level')
            ->orderBy('level')
            ->get();

        return response()->json([
            'distribution' => $distribution,
            'max_level'    => LevelService::getMaxLevel(),
        ]);
    }
}
