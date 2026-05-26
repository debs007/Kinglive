<?php

namespace App\Jobs;

use App\Models\MonthlyHostSnapshot;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Runs on the 1st of every month at midnight.
 *
 * 1. Snapshots every host's monthly stats into monthly_host_snapshots
 * 2. Resets monthly counters on users table:
 *    - total_live_minutes → 0
 *    - total_live_hours   → 0
 *    - video_live_days    → 0
 *    - audio_live_days    → 0
 *    - total_streams      → 0
 *    - diamond_balance    → 0  (earned diamonds reset — actual withdrawable
 *                               balance is tracked via WithdrawalRequests)
 *
 * diamond_balance is reset to 0 so the profile always shows THIS MONTH's
 * earnings. Withdrawals are tracked separately.
 */
class MonthlyResetJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        // The period we are snapshotting = the month that just ended
        $periodEnd   = now()->subDay()->endOfMonth(); // last day of prev month
        $periodStart = $periodEnd->copy()->startOfMonth();
        $year        = (int) $periodStart->year;
        $month       = (int) $periodStart->month;

        $rate = (float) config('wallet.diamond_to_usd_rate', 0.001);

        Log::info("MonthlyResetJob: snapshotting {$year}-{$month}");

        // Only snapshot hosts (users who have ever gone live)
        $hosts = User::with('agency')
            ->where(function ($q) {
                $q->where('total_streams', '>', 0)
                  ->orWhere('total_live_minutes', '>', 0)
                  ->orWhere('diamond_balance', '>', 0);
            })
            ->get();

        $snapCount = 0;

        foreach ($hosts as $user) {
            try {
                $agency         = $user->agency;
                $commissionPct  = $agency ? (float) $agency->commission_pct : 0.0;

                // Monthly live minutes from actual ended rooms this month
                $liveMinutes = (int) \App\Models\Room::where('host_user_id', $user->id)
                    ->where('status', 'ended')
                    ->whereYear('started_at', $year)
                    ->whereMonth('started_at', $month)
                    ->get(['started_at', 'ended_at'])
                    ->sum(function ($room) {
                        $start = $room->started_at;
                        $end   = $room->ended_at ?? $room->updated_at;
                        return ($start && $end) ? $start->diffInMinutes($end) : 0;
                    });

                $diamonds      = max(0, (int) $user->diamond_balance);
                $usdAmount     = round($diamonds * $rate, 2);
                $commissionUsd = round($usdAmount * ($commissionPct / 100), 2);
                $netUsd        = round($usdAmount - $commissionUsd, 2);

                MonthlyHostSnapshot::updateOrCreate(
                    ['user_id' => $user->id, 'year' => $year, 'month' => $month],
                    [
                        'period_start'          => $periodStart->toDateString(),
                        'period_end'            => $periodEnd->toDateString(),
                        'username'              => $user->username,
                        'display_name'          => $user->display_name,
                        'email'                 => $user->email,
                        'phone'                 => $user->phone,
                        'agency_id'             => $agency?->id,
                        'agency_name'           => $agency?->name,
                        'agency_commission_pct' => $commissionPct,
                        'diamonds_earned'       => $diamonds,
                        'diamond_balance'       => $diamonds,
                        'total_live_minutes'    => $liveMinutes,
                        'total_live_hours'      => (int) floor($liveMinutes / 60),
                        'video_live_days'       => (int) ($user->video_live_days ?? 0),
                        'audio_live_days'       => (int) ($user->audio_live_days ?? 0),
                        'total_streams'         => (int) ($user->total_streams ?? 0),
                        'usd_amount'            => $usdAmount,
                        'commission_usd'        => $commissionUsd,
                        'net_usd'               => $netUsd,
                        'created_at'            => now(),
                    ]
                );

                $snapCount++;
            } catch (\Exception $e) {
                Log::error("MonthlyResetJob: snapshot failed for user {$user->id}: {$e->getMessage()}");
            }
        }

        Log::info("MonthlyResetJob: {$snapCount} snapshots saved. Resetting counters...");

        // ── Reset monthly counters on all users ────────────────────────────────
        DB::table('users')->update([
            'diamond_balance'    => 0,
            'total_live_minutes' => 0,
            'total_live_hours'   => 0,
            'video_live_days'    => 0,
            'audio_live_days'    => 0,
            'total_streams'      => 0,
        ]);

        Log::info("MonthlyResetJob: complete for {$year}-{$month}.");
    }
}
