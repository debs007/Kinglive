<?php

namespace App\Jobs;

use App\Models\PkSession;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Redis;

class EndPkSessionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly string $pkSessionId) {}

    public function handle(): void
    {
        $raw = Redis::get("pk:session:{$this->pkSessionId}");
        if (! $raw) {
            return;
        }

        $data  = json_decode($raw, true);
        $roomA = $data['challenger_room'];
        $roomB = $data['target_room'];

        $scoreA = $data['scores'][$roomA] ?? 0;
        $scoreB = $data['scores'][$roomB] ?? 0;
        $winner = $scoreA >= $scoreB ? $roomA : $roomB;

        PkSession::where('id', $this->pkSessionId)->update([
            'score_a'        => $scoreA,
            'score_b'        => $scoreB,
            'winner_room_id' => $winner,
            'status'         => 'ended',
            'ended_at'       => now(),
        ]);

        Redis::del(
            "pk:room:{$roomA}",
            "pk:room:{$roomB}",
            "pk:session:{$this->pkSessionId}",
        );

        $payload = json_encode([
            'type'            => 'pk.ended',
            'pk_session_id'   => $this->pkSessionId,
            'challenger_room' => $roomA,
            'target_room'     => $roomB,
            'winner_room_id'  => $winner,
            'final_scores'    => [$roomA => $scoreA, $roomB => $scoreB],
        ]);

        // Push pk.ended to both rooms via Redis pending broadcast
        // The WS ping handler picks this up within 5 seconds
        Redis::setex("room:{$roomA}:pending_broadcast", 120, $payload);
        Redis::setex("room:{$roomB}:pending_broadcast", 120, $payload);

        // Also push to a global list that the WS server checks on every message
        Redis::lpush("ws:pending_broadcasts", json_encode([
            'rooms'   => [$roomA, $roomB],
            'payload' => $payload,
            'expires' => time() + 120,
        ]));
        Redis::expire("ws:pending_broadcasts", 300);
    }
}
