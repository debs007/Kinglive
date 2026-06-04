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

    private int $rewardAmount;
    private const MIN_DURATION = 40; // minutes

    public function handle(): void
    {
        $this->rewardAmount = (int) config('wallet.live_reward_diamonds', 5000);

        $today    = now()->toDateString();
        $dayStart = now()->startOfDay();   // Dhaka start of day via APP_TIMEZONE
        $dayEnd   = now()->endOfDay();     // Dhaka end of day via APP_TIMEZONE

        // Users already rewarded today — exclude them
        $alreadyRewarded = LiveReward::where('reward_date', $today)
            ->pluck('user_id')
            ->toArray();

        // Find eligible rooms:
        // - Status: ended ONLY (don't reward mid-stream)
        // - Ended today (covers streams that started yesterday and ended after midnight)
        // - Duration >= 40 minutes
        // - Host not already rewarded today
        $eligibleRooms = Room::where('status', 'ended')
            ->where('type', 'video')          // video rooms only
            ->whereNotNull('started_at')
            ->whereNotNull('ended_at')
            ->whereNotIn('host_user_id', $alreadyRewarded ?: [0])
            ->whereBetween('ended_at', [$dayStart, $dayEnd])
            ->get(['id', 'host_user_id', 'started_at', 'ended_at', 'status'])
            ->filter(fn($r) =>
                (int) $r->started_at->diffInMinutes($r->ended_at) >= self::MIN_DURATION
            );

        Log::info('CreditLiveRewardJob: found ' . $eligibleRooms->count() . ' eligible rooms', [
            'today'           => $today,
            'already_rewarded'=> count($alreadyRewarded),
            'room_ids'        => $eligibleRooms->pluck('id')->toArray(),
        ]);

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
                    'amount'      => $this->rewardAmount,
                    'credited_at' => now(),
                ]);

                User::where('id', $room->host_user_id)
                    ->increment('diamond_balance', $this->rewardAmount);

                $user = User::find($room->host_user_id);
                CoinTransaction::create([
                    'user_id'       => $room->host_user_id,
                    'type'          => 'live_reward',
                    'amount'        => $this->rewardAmount,
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