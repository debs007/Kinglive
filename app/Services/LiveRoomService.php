<?php

namespace App\Services;

use App\Models\Room;
use App\Models\RoomSeat;
use Illuminate\Support\Facades\Redis;

class LiveRoomService
{
    public function getRoomState(string $roomId): array
    {
        $room = Room::with([
            'host:id,username,avatar_url,level,is_verified',
            'seats.user:id,username,avatar_url',
        ])->find($roomId);

        $viewers   = (int) (Redis::get("room:{$roomId}:viewers") ?? 0);
        $seats     = Redis::hgetall("room:{$roomId}:seats") ?: [];
        $chatRaw   = Redis::zrange("room:{$roomId}:chat", -50, -1);
        $chat      = array_map(fn ($c) => json_decode($c, true), $chatRaw);
        $pkSession = $this->getPkSession($roomId);

        return [
            'room'         => $room,
            'viewer_count' => $viewers,
            'seats'        => $seats,
            'recent_chat'  => array_values($chat),
            'pk_session'   => $pkSession,
        ];
    }

    public function getViewerCount(string $roomId): int
    {
        return (int) (Redis::get("room:{$roomId}:viewers") ?? 0);
    }

    public function recordHeartbeat(string $roomId, int $userId): void
    {
        $key = "room:{$roomId}:heartbeats";
        Redis::zadd($key, time(), $userId);
        Redis::expire($key, 120);

        $active = Redis::zcount($key, time() - 60, '+inf');
        Redis::set("room:{$roomId}:viewers", $active);

        Room::where('id', $roomId)->update(['viewer_count' => $active]);
    }

    public function cleanupRoom(string $roomId): void
    {
        Redis::del(
            "room:{$roomId}:fds",
            "room:{$roomId}:viewers",
            "room:{$roomId}:seats",
            "room:{$roomId}:chat",
            "room:{$roomId}:heartbeats",
        );

        $pkSessionId = Redis::get("pk:room:{$roomId}");
        if ($pkSessionId) {
            Redis::del("pk:room:{$roomId}", "pk:session:{$pkSessionId}");
        }
    }

    public function getSummary(Room $room): array
    {
        $minutes = ($room->started_at && $room->ended_at)
            ? $room->started_at->diffInMinutes($room->ended_at)
            : 0;

        return [
            'duration_minutes'     => $minutes,
            'peak_viewers'         => $room->peak_viewer_count,
            'total_gifts_received' => $room->total_gifts_received,
        ];
    }

    public function initSeats(string $roomId, int $count): void
    {
        $rows = [];
        for ($i = 0; $i < $count; $i++) {
            $rows[] = [
                'room_id'    => $roomId,
                'seat_index' => $i,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        RoomSeat::insert($rows);
    }

    private function getPkSession(string $roomId): ?array
    {
        $id = Redis::get("pk:room:{$roomId}");
        if (! $id) {
            return null;
        }
        $raw = Redis::get("pk:session:{$id}");

        return $raw ? json_decode($raw, true) : null;
    }
}
