<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class DailyRewardController extends Controller
{
    // Fixed daily reward amount
    private const DAILY_DIAMONDS = 5000;

    /**
     * GET /daily-reward
     * Returns current week's reward status for the authenticated user.
     */
    public function status(): JsonResponse
    {
        $user   = auth()->user();
        $today  = now()->toDateString();

        // Build week starting from most recent Monday
        $monday    = now()->startOfWeek()->toDateString();
        $weekDays  = [];

        for ($i = 0; $i < 7; $i++) {
            $date      = now()->startOfWeek()->addDays($i)->toDateString();
            $rewardKey = "diamond_reward_given:{$user->id}:{$date}";
            $collected = (bool) Redis::get($rewardKey);

            // Also check DB as fallback (in case Redis was flushed)
            if (! $collected) {
                $collected = DB::table('coin_transactions')
                    ->where('user_id', $user->id)
                    ->where('type', 'live_reward')
                    ->where('reference', 'LIKE', "%{$date}%")
                    ->exists();

                // Re-populate Redis if found in DB
                if ($collected) {
                    Redis::setex($rewardKey, 86400 * 2, 1);
                }
            }

            $weekDays[] = [
                'day'       => $i + 1,           // 1=Mon, 7=Sun
                'date'      => $date,
                'diamonds'  => self::DAILY_DIAMONDS,
                'collected' => $collected,
                'is_today'  => $date === $today,
                'is_future' => $date > $today,
            ];
        }

        // Check if today's live reward is achievable
        // (did user do 40+ min video live today and not yet collected)
        $todayCollected  = collect($weekDays)->firstWhere('is_today', true)['collected'] ?? false;
        $todayLiveMinKey = "live_minutes_today:{$user->id}:" . now()->toDateString();
        $liveMinToday    = (int) (Redis::get($todayLiveMinKey) ?? 0);

        return response()->json([
            'week'            => $weekDays,
            'today_collected' => $todayCollected,
            'live_mins_today' => $liveMinToday,
            'mins_needed'     => max(0, 40 - $liveMinToday),
            'diamond_balance' => $user->diamond_balance,
        ]);
    }
}