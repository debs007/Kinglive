<?php

namespace App\Jobs;

use App\Models\Room;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * Fired 60 seconds after host WS disconnects.
 * If host reconnected within that window, grace key is gone — we skip.
 * If host did not reconnect, we end the room properly.
 */
class EndHostRoomJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly string $roomId,
        private readonly int    $hostUserId,
    ) {}

    public function handle(): void
    {
        $graceKey = "room:{$this->roomId}:host_grace";

        // If grace key still exists — host did NOT reconnect — end the room
        if (! Redis::exists($graceKey)) {
            Log::info("EndHostRoomJob: host reconnected, skipping room={$this->roomId}");
            return;
        }

        // Grace key still there — host is truly gone — end the room
        Redis::del($graceKey);

        $room = Room::find($this->roomId);
        if (! $room || $room->status !== 'live') {
            Log::info("EndHostRoomJob: room already ended room={$this->roomId}");
            return;
        }

        $room->update(['status' => 'ended', 'ended_at' => now()]);

        // Clean up Redis room data
        Redis::del(
            "room:{$this->roomId}:fds",
            "room:{$this->roomId}:viewers",
            "room:{$this->roomId}:seats",
            "room:{$this->roomId}:host_heartbeat",
            "room:{$this->roomId}:heartbeats",
        );

        Log::info("EndHostRoomJob: room ended after grace period room={$this->roomId}");

        // Broadcast room.ended via Redis so Swoole picks it up
        Redis::lpush('ws:pending_broadcasts', json_encode([
            'rooms'   => [$this->roomId],
            'payload' => json_encode([
                'type'    => 'room.ended',
                'room_id' => $this->roomId,
            ]),
            'expires' => time() + 30,
        ]));
    }
}
