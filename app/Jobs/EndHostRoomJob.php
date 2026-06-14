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
 *
 * Uses a unique token per disconnect cycle to avoid false-positives.
 * Scenario prevented:
 *   - Host drops at T=12min  → Job A dispatched with token "abc"
 *   - Host reconnects        → grace key deleted
 *   - Host drops again T=22  → Job B dispatched with token "xyz", grace key = "xyz"
 *   - Job A fires            → grace key = "xyz" ≠ "abc" → SKIP ✅
 *   - Job B fires            → grace key = "xyz" = "xyz"  → END  ✅
 */
class EndHostRoomJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly string $roomId,
        private readonly int    $hostUserId,
        private readonly string $token,        // unique per disconnect cycle
    ) {}

    public function handle(): void
    {
        $graceKey     = "room:{$this->roomId}:host_grace";
        $storedToken  = Redis::get($graceKey);

        // Token mismatch — host reconnected and disconnected again
        // A newer job is already queued for the latest disconnect — skip
        if ($storedToken !== $this->token) {
            Log::info("EndHostRoomJob: token mismatch, skipping room={$this->roomId} expected={$this->token} stored={$storedToken}");
            return;
        }

        // Token matches — this is the most recent disconnect — end the room
        Redis::del($graceKey);

        $room = Room::find($this->roomId);
        if (! $room || $room->status !== 'live') {
            Log::info("EndHostRoomJob: room already ended room={$this->roomId}");
            return;
        }

        $room->update(['status' => 'ended', 'ended_at' => now()]);

        Redis::del(
            "room:{$this->roomId}:fds",
            "room:{$this->roomId}:viewers",
            "room:{$this->roomId}:seats",
            "room:{$this->roomId}:host_heartbeat",
            "room:{$this->roomId}:heartbeats",
        );

        Log::info("EndHostRoomJob: room ended after grace period room={$this->roomId}");

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