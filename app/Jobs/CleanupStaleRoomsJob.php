<?php

namespace App\Jobs;

use App\Models\CoinTransaction;
use App\Models\Room;
use App\Models\User;
use App\Services\LiveRoomService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * Finds live rooms whose host has not sent a heartbeat recently
 * and marks them as ended. Runs every minute via the scheduler.
 *
 * A room is considered stale when:
 *   - status = 'live'
 *   - started_at > 2 minutes ago (grace period for new streams)
 *   - No heartbeat received in the last 2 minutes
 */
class CleanupStaleRoomsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // How many seconds without a heartbeat before a room is stale
    private const HEARTBEAT_TIMEOUT = 120; // 2 minutes

    // Grace period after stream starts before we start checking
    private const GRACE_PERIOD = 120; // 2 minutes

    public function handle(LiveRoomService $roomService): void
    {
        $liveRooms = Room::where('status', 'live')
            ->where('started_at', '<=', now()->subSeconds(self::GRACE_PERIOD))
            ->get(['id', 'host_user_id', 'title']);

        foreach ($liveRooms as $room) {
            // Clean stale seats — users who are in Redis seats but no longer connected
            $this->cleanStaleSeats($room->id);

            $stale = $this->isHostHeartbeatStale($room->id, $room->host_user_id)
                  || $this->hasNoViewers($room->id);

            if ($stale) {
                $this->endStaleRoom($room, $roomService);
            }
        }
    }

    private function cleanStaleSeats(string $roomId): void
    {
        $seats = Redis::hgetall("room:{$roomId}:seats") ?: [];
        foreach ($seats as $seatIdx => $seatJson) {
            $seatData = json_decode($seatJson, true);
            if (! isset($seatData['user_id'])) continue;
            $seatUserId = (int) $seatData['user_id'];

            // Check if user still has an active WS connection in this room
            $userFds  = Redis::smembers("ws:user:{$seatUserId}:fds") ?: [];
            $roomFds  = Redis::smembers("room:{$roomId}:fds") ?: [];
            $inRoom   = !empty(array_intersect($userFds, $roomFds));

            if (! $inRoom) {
                Redis::hdel("room:{$roomId}:seats", $seatIdx);
                Log::info("CleanupSeats: cleared stale seat {$seatIdx} in room {$roomId} for user {$seatUserId}");
            }
        }
    }

    private function isHostHeartbeatStale(string $roomId, int $hostUserId): bool
    {
        // Check host-specific heartbeat key
        $lastBeat = Redis::get("room:{$roomId}:host_heartbeat");

        if ($lastBeat === null) {
            // No heartbeat key at all — check if room has any WS connections
            $fdsCount = Redis::scard("room:{$roomId}:fds");
            // If no connections and no heartbeat, consider stale
            return $fdsCount === 0;
        }

        $secondsAgo = time() - (int) $lastBeat;
        return $secondsAgo > self::HEARTBEAT_TIMEOUT;
    }

    private function hasNoViewers(string $roomId): bool
    {
        $viewerCount = (int) (Redis::get("room:{$roomId}:viewers") ?? 0);
        $fdsCount    = (int) Redis::scard("room:{$roomId}:fds");
        // Room is empty if no viewers and no WS connections at all
        return $viewerCount === 0 && $fdsCount === 0;
    }

    private function endStaleRoom(Room $room, LiveRoomService $roomService): void
    {
        Log::info("CleanupStaleRooms: ending stale room {$room->id} '{$room->title}'");

        $endedAt = now();
        $room->update(['status' => 'ended', 'ended_at' => $endedAt]);

        // ── Update host live stats ────────────────────────────────────────
        $host = User::find($room->host_user_id);
        if ($host) {
            $startedAt    = $room->started_at ?? $room->created_at;
            $durationMins = (int) $startedAt->diffInMinutes($endedAt);
            $durationHours = (int) floor($durationMins / 60);

            $updates = [
                'total_live_minutes' => \DB::raw("total_live_minutes + {$durationMins}"),
                'total_live_hours'   => \DB::raw("total_live_hours + {$durationHours}"),
                'total_streams'      => \DB::raw('total_streams + 1'),
            ];

            if ($durationMins >= 40) {
                $type    = $room->type;
                $dayKey  = "live_day_counted:{$host->id}:{$type}:" . $endedAt->toDateString();
                if (! Redis::get($dayKey)) {
                    Redis::setex($dayKey, 86400, 1);
                    if ($type === 'video') {
                        $updates['video_live_days'] = \DB::raw('video_live_days + 1');
                    } else {
                        $updates['audio_live_days'] = \DB::raw('audio_live_days + 1');
                    }
                }

                // Diamond reward for 40+ min video stream
                // SETNX is atomic — prevents double credit if both end() and cleanup run
                if ($type === 'video') {
                    $rewardKey = "diamond_reward_given:{$host->id}:" . $endedAt->toDateString();
                    try {
                        $claimed = Redis::set($rewardKey, 1, 'EX', 86400 * 2, 'NX');
                    } catch (\Exception $e) {
                        $claimed = ! Redis::exists($rewardKey);
                        if ($claimed) Redis::setex($rewardKey, 86400 * 2, 1);
                    }
                    if ($claimed) {
                        $host->increment('diamond_balance', 5000);
                        CoinTransaction::create([
                            'user_id'      => $host->id,
                            'type'         => 'live_reward',
                            'amount'       => 5000,
                            'balance_after'=> $host->fresh()->diamond_balance,
                            'reference'    => "live_reward:room:{$room->id}",
                        ]);
                        Log::info("CleanupStaleRooms: rewarded {$host->id} 5000 diamonds for room {$room->id}");
                    }
                }
            }

            $host->update($updates);
        }

        $roomService->cleanupRoom($room->id);
    }
}