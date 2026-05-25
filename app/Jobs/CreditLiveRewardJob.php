<?php

namespace App\Jobs;

use App\Models\LiveReward;
use App\Models\Room;
use App\Models\User;
use App\Models\CoinTransaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Runs every minute via scheduler.
 * Credits 5000 diamonds to hosts who streamed >= 40 mins today.
 * Once per user per day — enforced by live_rewards unique(user_id, reward_date).
 */
class CreditLiveRewardJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const REWARD_AMOUNT = 5000;
    private const MIN_DURATION  = 40; // minutes

    public function handle(): void
    {
        $today     = now()->toDateString();
        $yesterday = now()->subDay()->toDateString();

        // Users already rewarded today — exclude them
        $alreadyRewarded = LiveReward::where('reward_date', $today)
            ->pluck('user_id')
            ->toArray();

        // Find eligible rooms:
        // - Status: live OR ended (host may have ended before job ran)
        // - Duration >= 40 minutes
        // - Started today OR yesterday (catches midnight boundary)
        // - Host not already rewarded today
        $eligibleRooms = Room::whereIn('status', ['live', 'ended'])
            ->whereNotNull('started_at')
            ->whereNotIn('host_user_id', $alreadyRewarded)
            ->whereIn(DB::raw('DATE(started_at)'), [$today, $yesterday])
            ->where(function ($q) {
                $q->where(function ($q2) {
                    // Still live — check duration using current time
                    $q2->where('status', 'live')
                       ->whereRaw(
                           'TIMESTAMPDIFF(MINUTE, started_at, NOW()) >= ?',
                           [self::MIN_DURATION]
                       );
                })->orWhere(function ($q2) {
                    // Ended — check duration using ended_at
                    $q2->where('status', 'ended')
                       ->whereNotNull('ended_at')
                       ->whereRaw(
                           'TIMESTAMPDIFF(MINUTE, started_at, ended_at) >= ?',
                           [self::MIN_DURATION]
                       );
                });
            })
            ->get(['id', 'host_user_id', 'started_at', 'ended_at', 'status']);

        foreach ($eligibleRooms as $room) {
            $this->creditReward($room, $today);
        }
    }

    private function creditReward(Room $room, string $today): void
    {
        try {
            DB::transaction(function () use ($room, $today) {
                LiveReward::create([
                    'user_id'     => $room->host_user_id,
                    'reward_date' => $today,
                    'room_id'     => $room->id,
                    'amount'      => self::REWARD_AMOUNT,
                    'credited_at' => now(),
                ]);

                User::where('id', $room->host_user_id)
                    ->increment('diamond_balance', self::REWARD_AMOUNT);

                $user = User::find($room->host_user_id);
                CoinTransaction::create([
                    'user_id'       => $room->host_user_id,
                    'type'          => 'live_reward',
                    'amount'        => self::REWARD_AMOUNT,
                    'balance_after' => $user?->diamond_balance ?? 0,
                    'reference'     => "live_reward:room:{$room->id}:{$today}",
                ]);

                Log::info("LiveReward credited: user={$room->host_user_id} room={$room->id} date={$today}");
            });
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            // Already credited — DB unique constraint handled the race condition
        } catch (\Exception $e) {
            Log::error("LiveReward failed: user={$room->host_user_id} error={$e->getMessage()}");
        }
    }
}