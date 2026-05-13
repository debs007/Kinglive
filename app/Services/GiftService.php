<?php

namespace App\Services;

use App\Models\CoinTransaction;
use App\Models\Gift;
use App\Models\GiftTransaction;
use App\Services\LevelService;
use App\Models\Room;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class GiftService
{
    public function sendGift(
        User   $sender,
        int    $giftId,
        string $roomId,
        int    $targetUserId,
        int    $quantity
    ): array {
        $gift         = Gift::findOrFail($giftId);
        $totalCoins   = $gift->coin_price * $quantity;
        $totalDiamonds = $gift->diamond_value * $quantity;

        if ($sender->coin_balance < $totalCoins) {
            return ['success' => false, 'message' => 'Insufficient coins'];
        }

        DB::transaction(function () use ($sender, $gift, $roomId, $targetUserId, $quantity, $totalCoins, $totalDiamonds) {
            // Deduct from sender
            $sender->decrement('coin_balance', $totalCoins);

            CoinTransaction::create([
                'user_id'      => $sender->id,
                'type'         => 'gift_sent',
                'amount'       => -$totalCoins,
                'balance_after' => $sender->fresh()->coin_balance,
                'reference'    => "gift:{$gift->id}:room:{$roomId}",
            ]);

            // Credit receiver
            User::where('id', $targetUserId)->increment('diamond_balance', $totalDiamonds);

            CoinTransaction::create([
                'user_id'      => $targetUserId,
                'type'         => 'gift_received',
                'amount'       => $totalDiamonds,
                'balance_after' => User::find($targetUserId)->diamond_balance,
                'reference'    => "gift:{$gift->id}:room:{$roomId}",
            ]);

            GiftTransaction::create([
                'sender_id'    => $sender->id,
                'receiver_id'  => $targetUserId,
                'gift_id'      => $gift->id,
                'room_id'      => $roomId,
                'quantity'     => $quantity,
                'coin_total'   => $totalCoins,
                'diamond_total' => $totalDiamonds,
            ]);

                Room::where('id', $roomId)->increment('total_gifts_received', $totalCoins);
        });

        // Update sender level AFTER transaction (non-blocking)
        $sender->refresh();
        $newLevel = LevelService::updateUserLevel($sender, $totalCoins);

        return [
            'success'        => true,
            'coins_deducted' => $totalCoins,
            'new_balance'    => $sender->fresh()->coin_balance,
            'new_level'      => $newLevel, // null if no level up
            'current_level'  => $sender->fresh()->level,
        ];
    }

    public function getTopGiftsReport(Carbon $from, Carbon $to, ?string $gameId = null): \Illuminate\Support\Collection
    {
        return GiftTransaction::whereBetween('created_at', [$from, $to])
            ->selectRaw('gift_id, SUM(quantity) as total_sent, SUM(coin_total) as total_coins, SUM(diamond_total) as total_diamonds')
            ->with('gift:id,name,thumbnail_url,rarity')
            ->groupBy('gift_id')
            ->orderByDesc('total_coins')
            ->limit(20)
            ->get();
    }

    public function getTopSenders(Carbon $from, Carbon $to, int $limit = 20): \Illuminate\Support\Collection
    {
        return GiftTransaction::whereBetween('created_at', [$from, $to])
            ->selectRaw('sender_id, SUM(coin_total) as total_coins, COUNT(*) as count')
            ->with('sender:id,username,avatar_url,level')
            ->groupBy('sender_id')
            ->orderByDesc('total_coins')
            ->limit($limit)
            ->get();
    }

    public function getTopReceivers(Carbon $from, Carbon $to, int $limit = 20): \Illuminate\Support\Collection
    {
        return GiftTransaction::whereBetween('created_at', [$from, $to])
            ->selectRaw('receiver_id, SUM(diamond_total) as total_diamonds, COUNT(*) as count')
            ->with('receiver:id,username,avatar_url,level')
            ->groupBy('receiver_id')
            ->orderByDesc('total_diamonds')
            ->limit($limit)
            ->get();
    }

    public function getDailySummary(Carbon $from, Carbon $to): \Illuminate\Support\Collection
    {
        return GiftTransaction::whereBetween('created_at', [$from, $to])
            ->selectRaw('DATE(created_at) as date, SUM(coin_total) as coins, SUM(diamond_total) as diamonds, COUNT(*) as transactions')
            ->groupBy('date')
            ->orderBy('date')
            ->get();
    }
}