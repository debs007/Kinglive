<?php

namespace App\Jobs;

use App\Models\Room;
use App\Services\LiveRoomService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * Finds live rooms whose host has missed heartbeats for too long.
 * Runs every minute via the scheduler.
 *
 * A room ends ONLY when:
 *   - Host heartbeat has been missing for > 5 minutes (HEARTBEAT_TIMEOUT)
 *   - Room started > 2 minutes ago (grace period for new streams)
 *
 * Rooms do NOT end because of:
 *   - Zero viewers (host can stream with no audience)
 *   - WS disconnect (handled by EndHostRoomJob grace period)
 *   - Participant/audience leaving (they just leave, room stays)
 */
class CleanupStaleRoomsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Host must miss heartbeats for 5 minutes before room is ended
    // Flutter sends heartbeat every 30s — 5 min = 10 missed beats
    private const HEARTBEAT_TIMEOUT = 300; // 5 minutes

    // Don't check rooms that just started
    private const GRACE_PERIOD = 120; // 2 minutes

    public function handle(LiveRoomService $roomService): void
    {
        $liveRooms = Room::where('status', 'live')
            ->where('started_at', '<=', now()->subSeconds(self::GRACE_PERIOD))
            ->get(['id', 'host_user_id', 'title', 'type']);

        foreach ($liveRooms as $room) {
            // Clean stale seats (user disconnected but still in seat)
            $this->cleanStaleSeats($room->id);

            // Only end room if heartbeat has been missing for too long
            // Never end room just because viewers = 0
            if ($this->isHeartbeatStaleTooLong($room->id)) {
                Log::info("CleanupStaleRooms: ending room {$room->id} — heartbeat missing > " . self::HEARTBEAT_TIMEOUT . "s");
                $room->update(['status' => 'ended', 'ended_at' => now()]);
                $roomService->cleanupRoom($room->id);
            }
        }
    }

    private function isHeartbeatStaleTooLong(string $roomId): bool
    {
        $lastBeat = Redis::get("room:{$roomId}:host_heartbeat");

        // No heartbeat key ever set — host may not have the heartbeat feature
        // Don't end the room, just let CreditLiveRewardJob/EndHostRoomJob handle it
        if ($lastBeat === null) {
            return false;
        }

        $secondsAgo = time() - (int) $lastBeat;
        return $secondsAgo > self::HEARTBEAT_TIMEOUT;
    }

    private function cleanStaleSeats(string $roomId): void
    {
        $seats = Redis::hgetall("room:{$roomId}:seats") ?: [];
        foreach ($seats as $seatIdx => $seatJson) {
            $seatData   = json_decode($seatJson, true);
            if (! isset($seatData['user_id'])) continue;
            $seatUserId = (int) $seatData['user_id'];

            $userFds = Redis::smembers("ws:user:{$seatUserId}:fds") ?: [];
            $roomFds = Redis::smembers("room:{$roomId}:fds") ?: [];
            $inRoom  = ! empty(array_intersect($userFds, $roomFds));

            if (! $inRoom) {
                Redis::hdel("room:{$roomId}:seats", $seatIdx);
                Log::info("CleanupSeats: cleared stale seat {$seatIdx} room={$roomId} user={$seatUserId}");
            }
        }
    }
}