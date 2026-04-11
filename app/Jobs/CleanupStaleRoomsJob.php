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
        $staleRooms = Room::where('status', 'live')
            ->where('started_at', '<=', now()->subSeconds(self::GRACE_PERIOD))
            ->get(['id', 'host_user_id', 'title']);

        foreach ($staleRooms as $room) {
            if ($this->isHostHeartbeatStale($room->id, $room->host_user_id)) {
                $this->endStaleRoom($room, $roomService);
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

    private function endStaleRoom(Room $room, LiveRoomService $roomService): void
    {
        Log::info("CleanupStaleRooms: ending stale room {$room->id} '{$room->title}'");

        $room->update([
            'status'   => 'ended',
            'ended_at' => now(),
        ]);

        $roomService->cleanupRoom($room->id);
    }
}
