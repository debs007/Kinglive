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

        Redis::publish("broadcast:room:{$roomA}", $payload);
        Redis::publish("broadcast:room:{$roomB}", $payload);
    }
}
