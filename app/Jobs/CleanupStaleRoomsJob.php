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
 * Runs every minute. Ends rooms whose host has disappeared.
 *
 * Handles two scenarios:
 *
 * A) NORMAL: heartbeat key exists but is old = host stopped sending
 *    → end after HEARTBEAT_TIMEOUT (5 min)
 *
 * B) POST-RESTART: heartbeat key missing (expired during server downtime)
 *    AND room has been "live" for longer than ORPHAN_TIMEOUT (10 min)
 *    → end it — host is clearly not there anymore
 *
 * Also broadcasts room.ended via ws:pending_broadcasts so the home screen
 * removes the room in real-time.
 */
class CleanupStaleRoomsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const HEARTBEAT_TIMEOUT = 300;  // 5 min — normal stale detection
    private const ORPHAN_TIMEOUT    = 600;  // 10 min — no heartbeat key at all (post-restart)
    private const GRACE_PERIOD      = 120;  // 2 min — don't touch brand-new rooms

    public function handle(LiveRoomService $roomService): void
    {
        $liveRooms = Room::where('status', 'live')
            ->where('started_at', '<=', now()->subSeconds(self::GRACE_PERIOD))
            ->get(['id', 'host_user_id', 'started_at', 'title', 'type']);

        foreach ($liveRooms as $room) {
            $this->cleanStaleSeats($room->id);

            if ($this->shouldEndRoom($room)) {
                Log::info("CleanupStaleRooms: ending room {$room->id} ({$room->title})");
                $room->update(['status' => 'ended', 'ended_at' => now()]);
                $roomService->cleanupRoom($room->id);

                // Broadcast room.ended so home screen removes it in real-time
                Redis::lpush('ws:pending_broadcasts', json_encode([
                    'rooms'   => ['__global__'],
                    'payload' => json_encode([
                        'type'    => 'room.removed',
                        'room_id' => $room->id,
                    ]),
                    'expires' => time() + 30,
                ]));
            }
        }
    }

    private function shouldEndRoom(Room $room): bool
    {
        $lastBeat = Redis::get("room:{$room->id}:host_heartbeat");

        if ($lastBeat !== null) {
            // Heartbeat key exists — check if it's too old
            $secondsAgo = time() - (int) $lastBeat;
            if ($secondsAgo > self::HEARTBEAT_TIMEOUT) {
                Log::info("CleanupStaleRooms: heartbeat stale {$secondsAgo}s ago room={$room->id}");
                return true;
            }
            return false;
        }

        // No heartbeat key — could be:
        // 1. Host never updated (old app version without heartbeat feature)
        // 2. Key expired after server restart
        // If room has been "live" for > ORPHAN_TIMEOUT with no heartbeat — end it
        $minutesLive = (int) $room->started_at->diffInMinutes(now());
        if ($minutesLive >= (self::ORPHAN_TIMEOUT / 60)) {
            Log::info("CleanupStaleRooms: orphaned room {$room->id} — no heartbeat, live {$minutesLive}min");
            return true;
        }

        return false;
    }

    private function cleanStaleSeats(string $roomId): void
    {
        $seats   = Redis::hgetall("room:{$roomId}:seats") ?: [];
        $roomFds = Redis::smembers("room:{$roomId}:fds") ?: [];

        foreach ($seats as $seatIdx => $seatJson) {
            $seatData   = json_decode($seatJson, true);
            if (! isset($seatData['user_id'])) continue;
            $seatUserId = (int) $seatData['user_id'];
            $userFds    = Redis::smembers("ws:user:{$seatUserId}:fds") ?: [];
            $inRoom     = ! empty(array_intersect($userFds, $roomFds));

            if (! $inRoom) {
                Redis::hdel("room:{$roomId}:seats", $seatIdx);
                Log::info("CleanupSeats: cleared stale seat {$seatIdx} room={$roomId} user={$seatUserId}");
            }
        }
    }
}