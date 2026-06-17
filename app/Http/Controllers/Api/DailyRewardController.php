<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CoinTransaction;
use App\Models\LiveReward;
use App\Models\Room;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DailyRewardController extends Controller
{
    private const MIN_LIVE_MINUTES = 40;
    private const REWARD_AMOUNT    = 5000; // overridden by config if set

    // ── GET /daily-reward ─────────────────────────────────────────────────────
    // Returns this week Mon→Sun with:
    //   - live minutes completed each day
    //   - whether they qualify (>= 40 mins video live)
    //   - whether they collected the reward for that day
    //   - collect button shown if qualified AND not yet collected

    public function status(): JsonResponse
    {
        $user   = auth()->user();
        $today  = now()->toDateString();
        $monday = now()->startOfWeek()->toDateString();
        $sunday = now()->endOfWeek()->toDateString();

        // Dates already collected this week
        $collectedDates = LiveReward::where('user_id', $user->id)
            ->whereBetween('reward_date', [$monday, $sunday])
            ->pluck('reward_date')
            ->map(fn($d) => (string) $d)
            ->toArray();

        // Build Mon→Sun with live minutes per day
        $weekDays = [];
        for ($i = 0; $i < 7; $i++) {
            $date     = now()->startOfWeek()->addDays($i)->toDateString();
            $dayStart = now()->startOfWeek()->addDays($i)->startOfDay();
            $dayEnd   = now()->startOfWeek()->addDays($i)->endOfDay();

            // Only past + today days can have live minutes
            $liveMinutes = 0;
            if ($date <= $today) {
                // Qualification = at least ONE continuous session >= 40 mins
                // Sum of multiple short sessions does NOT qualify
                $rooms = Room::where('host_user_id', $user->id)
                    ->where('type', 'video')
                    ->where('status', 'ended')
                    ->whereNotNull('ended_at')
                    ->whereBetween('ended_at', [$dayStart, $dayEnd])
                    ->get(['started_at', 'ended_at']);

                // Get the longest single session duration
                $liveMinutes = (int) $rooms
                    ->map(fn($r) => (int) $r->started_at->diffInMinutes($r->ended_at))
                    ->max() ?? 0;

                // If today and currently live — check running session too
                if ($date === $today) {
                    $live = Room::where('host_user_id', $user->id)
                        ->where('type', 'video')
                        ->where('status', 'live')
                        ->first(['started_at']);
                    if ($live) {
                        $runningMins = (int) $live->started_at->diffInMinutes(now());
                        $liveMinutes = max($liveMinutes, $runningMins);
                    }
                }
            }

            $qualified = $liveMinutes >= self::MIN_LIVE_MINUTES;
            $collected = in_array($date, $collectedDates);

            $weekDays[] = [
                'day'          => $i + 1,        // 1=Mon ... 7=Sun
                'date'         => $date,
                'diamonds'     => (int) config('wallet.live_reward_diamonds', self::REWARD_AMOUNT),
                'live_minutes' => $liveMinutes,   // actual minutes done
                'mins_needed'  => max(0, self::MIN_LIVE_MINUTES - $liveMinutes),
                'qualified'    => $qualified,     // >= 40 mins done
                'collected'    => $collected,     // reward already taken
                'can_collect'  => $qualified && !$collected && $date <= $today,
                'is_today'     => $date === $today,
                'is_future'    => $date > $today,
            ];
        }

        return response()->json([
            'week'            => $weekDays,
            'diamond_balance' => $user->diamond_balance,
            'week_start'      => $monday,
            'week_end'        => $sunday,
        ]);
    }

    // ── POST /daily-reward/collect ────────────────────────────────────────────
    // User manually collects reward for a specific day.
    // Requirements:
    //   - date must be within current week
    //   - date must not be in the future
    //   - user must have >= 40 mins of video live that day
    //   - not already collected for that day

    public function collect(Request $request): JsonResponse
    {
        $request->validate(['date' => ['required', 'date_format:Y-m-d']]);

        $date  = $request->input('date');
        $user  = auth()->user();
        $today = now()->toDateString();

        // Must be current week
        $monday = now()->startOfWeek()->toDateString();
        $sunday = now()->endOfWeek()->toDateString();
        if ($date < $monday || $date > $sunday) {
            return response()->json([
                'message' => 'You can only collect rewards for the current week.',
            ], 422);
        }

        // Cannot collect future days
        if ($date > $today) {
            return response()->json([
                'message' => 'Cannot collect reward for a future day.',
            ], 422);
        }

        // Check already collected
        $alreadyCollected = LiveReward::where('user_id', $user->id)
            ->where('reward_date', $date)
            ->exists();

        if ($alreadyCollected) {
            return response()->json([
                'message' => 'Reward already collected for this day.',
            ], 422);
        }

        // Check live minutes for that specific day
        $dayStart    = now()->parse($date)->startOfDay();
        $dayEnd      = now()->parse($date)->endOfDay();
        // Must have at least ONE continuous session >= 40 mins
        $rooms = Room::where('host_user_id', $user->id)
            ->where('type', 'video')
            ->where('status', 'ended')
            ->whereNotNull('ended_at')
            ->whereBetween('ended_at', [$dayStart, $dayEnd])
            ->get(['started_at', 'ended_at']);

        $liveMinutes = (int) ($rooms
            ->map(fn($r) => (int) $r->started_at->diffInMinutes($r->ended_at))
            ->max() ?? 0);

        if ($liveMinutes < self::MIN_LIVE_MINUTES) {
            return response()->json([
                'message'      => "You need at least 40 minutes of video live to collect this reward.",
                'live_minutes' => $liveMinutes,
                'mins_needed'  => self::MIN_LIVE_MINUTES - $liveMinutes,
            ], 422);
        }

        // Credit the reward
        $rewardAmount = (int) config('wallet.live_reward_diamonds', self::REWARD_AMOUNT);

        try {
            DB::transaction(function () use ($user, $date, $rewardAmount) {
                // Insert live_reward — unique(user_id, reward_date) prevents double collect
                LiveReward::create([
                    'user_id'     => $user->id,
                    'reward_date' => $date,
                    'room_id'     => null, // manual collect — no specific room
                    'amount'      => $rewardAmount,
                ]);

                // Credit diamonds
                $user->increment('diamond_balance', $rewardAmount);
                $newBalance = $user->fresh()->diamond_balance;

                // Transaction record
                CoinTransaction::create([
                    'user_id'       => $user->id,
                    'type'          => 'live_reward',
                    'amount'        => $rewardAmount,
                    'balance_after' => $newBalance,
                    'reference'     => "live_reward:manual:{$date}",
                ]);
            });
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            // Double-tap protection — already collected
            return response()->json(['message' => 'Already collected.'], 422);
        }

        return response()->json([
            'message'         => 'Reward collected!',
            'diamonds_earned' => $rewardAmount,
            'diamond_balance' => $user->fresh()->diamond_balance,
            'date'            => $date,
        ]);
    }
}