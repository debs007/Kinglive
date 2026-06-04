<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LiveReward;
use Illuminate\Http\JsonResponse;

class DailyRewardController extends Controller
{
    private const DAILY_DIAMONDS = 5000;

    /**
     * GET /daily-reward
     * Returns current week's reward status for the authenticated user.
     * Uses live_rewards table as source of truth.
     */
    public function status(): JsonResponse
    {
        $user  = auth()->user();
        $today = now()->toDateString();

        // Get all live_rewards for this user this week
        $monday    = now()->startOfWeek()->toDateString();   // Monday
        $sunday    = now()->endOfWeek()->toDateString();     // Sunday

        $collectedDates = LiveReward::where('user_id', $user->id)
            ->whereBetween('reward_date', [$monday, $sunday])
            ->pluck('reward_date')
            ->map(fn($d) => (string) $d)
            ->toArray();

        // Build week Mon→Sun
        $weekDays = [];
        for ($i = 0; $i < 7; $i++) {
            $date = now()->startOfWeek()->addDays($i)->toDateString();

            $weekDays[] = [
                'day'       => $i + 1,          // 1=Mon ... 7=Sun
                'date'      => $date,
                'diamonds'  => self::DAILY_DIAMONDS,
                'collected' => in_array($date, $collectedDates),
                'is_today'  => $date === $today,
                'is_future' => $date > $today,
            ];
        }

        $todayCollected = in_array($today, $collectedDates);

        // Calculate today's live minutes from actual ended rooms
        // More reliable than Redis which can expire or get flushed
        $liveMinToday = (int) \App\Models\Room::where('host_user_id', $user->id)
            ->where('status', 'ended')
            ->whereDate('started_at', $today)
            ->whereNotNull('ended_at')
            ->get(['started_at', 'ended_at'])
            ->sum(function ($room) {
                return $room->started_at->diffInMinutes($room->ended_at);
            });

        // Also add currently live room minutes if streaming right now
        $liveRoom = \App\Models\Room::where('host_user_id', $user->id)
            ->where('status', 'live')
            ->whereDate('started_at', $today)
            ->first();
        if ($liveRoom) {
            $liveMinToday += (int) $liveRoom->started_at->diffInMinutes(now());
        }

        return response()->json([
            'week'            => $weekDays,
            'today_collected' => $todayCollected,
            'live_mins_today' => $liveMinToday,
            'mins_needed'     => max(0, 40 - $liveMinToday),
            'diamond_balance' => $user->diamond_balance,
        ]);
    }
}