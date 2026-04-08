<?php

namespace App\Swoole;

use App\Models\Room;
use App\Services\BanService;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Swoole\Http\Request;
use Swoole\WebSocket\Frame;
use Swoole\WebSocket\Server;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * King Live — Swoole WebSocket Handler
 *
 * Registered in config/octane.php under swoole.options.
 * All real-time events (chat, gifts, PK, seats, moderation)
 * flow through this handler backed by Redis Pub/Sub.
 */
class WebSocketHandler
{
    /** fd => [user_id, room_id, username, avatar] */
    private static array $connections = [];

    // ── Connection lifecycle ──────────────────────────────────────────────────

    public static function onOpen(Server $server, Request $request): void
    {
        $fd    = $request->fd;
        $token = $request->get['token'] ?? null;

        if (! $token) {
            $server->close($fd);
            return;
        }

        try {
            $user = JWTAuth::setToken($token)->authenticate();
        } catch (\Throwable) {
            $server->push($fd, json_encode(['type' => 'error', 'message' => 'Unauthorized.']));
            $server->close($fd);
            return;
        }

        if (! $user) {
            $server->close($fd);
            return;
        }

        /** @var BanService $banService */
        $banService = app(BanService::class);

        if ($banService->isGloballyBanned($user->id)) {
            $server->push($fd, json_encode(['type' => 'banned', 'message' => 'Your account is suspended.']));
            $server->close($fd);
            return;
        }

        static::$connections[$fd] = [
            'user_id'  => $user->id,
            'room_id'  => null,
            'username' => $user->username,
            'avatar'   => $user->avatar_url,
            'level'    => $user->level,
        ];

        $server->push($fd, json_encode(['type' => 'connected', 'user_id' => $user->id]));

        Log::info("WS: connected fd={$fd} user_id={$user->id}");
    }

    public static function onMessage(Server $server, Frame $frame): void
    {
        $fd   = $frame->fd;
        $conn = static::$connections[$fd] ?? null;

        if (! $conn) {
            return;
        }

        $data = json_decode($frame->data, true);

        if (! $data || ! isset($data['type'])) {
            return;
        }

        match ($data['type']) {
            'room.join'       => static::handleRoomJoin($server, $fd, $conn, $data),
            'room.leave'      => static::handleRoomLeave($server, $fd, $conn),
            'chat.message'    => static::handleChat($server, $fd, $conn, $data),
            'seat.request'    => static::handleSeatRequest($server, $fd, $conn, $data),
            'seat.response'   => static::handleSeatResponse($server, $fd, $conn, $data),
            'seat.leave'      => static::handleSeatLeave($server, $fd, $conn, $data),
            'gift.send'       => static::handleGift($server, $fd, $conn, $data),
            'pk.invite'       => static::handlePkInvite($server, $fd, $conn, $data),
            'pk.response'     => static::handlePkResponse($server, $fd, $conn, $data),
            'game.event'      => static::handleGameEvent($server, $fd, $conn, $data),
            'mod.kick'        => static::handleKick($server, $fd, $conn, $data),
            'mod.silence'     => static::handleSilence($server, $fd, $conn, $data),
            'ping'            => $server->push($fd, json_encode(['type' => 'pong'])),
            default           => null,
        };
    }

    public static function onClose(Server $server, int $fd): void
    {
        $conn = static::$connections[$fd] ?? null;

        if (! $conn) {
            return;
        }

        if ($conn['room_id']) {
            static::removeFromRoom($server, $fd, $conn);
        }

        unset(static::$connections[$fd]);

        Log::info("WS: disconnected fd={$fd} user_id={$conn['user_id']}");
    }

    // ── Room ──────────────────────────────────────────────────────────────────

    private static function handleRoomJoin(Server $server, int $fd, array &$conn, array $data): void
    {
        $roomId = $data['room_id'] ?? null;

        if (! $roomId) {
            return;
        }

        $room = Room::find($roomId);

        if (! $room || $room->status !== 'live') {
            $server->push($fd, json_encode(['type' => 'error', 'message' => 'Room not found or not live.']));
            return;
        }

        /** @var BanService $banService */
        $banService = app(BanService::class);

        if ($banService->isRoomBanned($conn['user_id'], $roomId)) {
            $server->push($fd, json_encode(['type' => 'room.banned', 'room_id' => $roomId]));
            return;
        }

        $conn['room_id'] = $roomId;

        Redis::sadd("room:{$roomId}:fds", $fd);
        Redis::expire("room:{$roomId}:fds", 86400);
        Redis::incr("room:{$roomId}:viewers");

        // Broadcast user joined
        static::broadcastToRoom($server, $roomId, [
            'type'     => 'user.joined',
            'user_id'  => $conn['user_id'],
            'username' => $conn['username'],
            'avatar'   => $conn['avatar'],
            'level'    => $conn['level'],
        ], exclude: $fd);

        // Send room state to new joiner
        $chatRaw = Redis::zrange("room:{$roomId}:chat", -50, -1);
        $chat    = array_map(fn ($c) => json_decode($c, true), $chatRaw);

        $server->push($fd, json_encode([
            'type'         => 'room.state',
            'viewer_count' => (int) Redis::get("room:{$roomId}:viewers"),
            'recent_chat'  => array_values($chat),
            'seats'        => Redis::hgetall("room:{$roomId}:seats") ?: [],
        ]));
    }

    private static function handleRoomLeave(Server $server, int $fd, array &$conn): void
    {
        if ($conn['room_id']) {
            static::removeFromRoom($server, $fd, $conn);
        }
    }

    private static function removeFromRoom(Server $server, int $fd, array &$conn): void
    {
        $roomId = $conn['room_id'];

        Redis::srem("room:{$roomId}:fds", $fd);

        $remaining = (int) Redis::decr("room:{$roomId}:viewers");

        if ($remaining < 0) {
            Redis::set("room:{$roomId}:viewers", 0);
        }

        static::broadcastToRoom($server, $roomId, [
            'type'     => 'user.left',
            'user_id'  => $conn['user_id'],
            'username' => $conn['username'],
        ]);

        $conn['room_id'] = null;
    }

    // ── Chat ──────────────────────────────────────────────────────────────────

    private static function handleChat(Server $server, int $fd, array $conn, array $data): void
    {
        $roomId = $conn['room_id'];

        if (! $roomId) {
            return;
        }

        // Check silence
        if (Redis::exists("silence:{$conn['user_id']}:{$roomId}")) {
            $server->push($fd, json_encode(['type' => 'error', 'message' => 'You are silenced in this room.']));
            return;
        }

        $message = htmlspecialchars(mb_substr($data['message'] ?? '', 0, 500));

        if ($message === '') {
            return;
        }

        $payload = json_encode([
            'user_id'  => $conn['user_id'],
            'username' => $conn['username'],
            'avatar'   => $conn['avatar'],
            'level'    => $conn['level'],
            'message'  => $message,
            'ts'       => time(),
        ]);

        // Keep last 200 messages
        $key = "room:{$roomId}:chat";
        Redis::zadd($key, time(), $payload);
        Redis::zremrangebyrank($key, 0, -201);

        static::broadcastToRoom($server, $roomId, [
            'type'     => 'chat.message',
            'user_id'  => $conn['user_id'],
            'username' => $conn['username'],
            'avatar'   => $conn['avatar'],
            'level'    => $conn['level'],
            'message'  => $message,
        ]);
    }

    // ── Seats ─────────────────────────────────────────────────────────────────

    private static function handleSeatRequest(Server $server, int $fd, array $conn, array $data): void
    {
        $roomId    = $conn['room_id'];
        $seatIndex = (int) ($data['seat_index'] ?? -1);

        if (! $roomId || $seatIndex < 0) {
            return;
        }

        $hostFd = static::getHostFd($roomId);

        if ($hostFd && $server->isEstablished($hostFd)) {
            $server->push($hostFd, json_encode([
                'type'        => 'seat.request',
                'user_id'     => $conn['user_id'],
                'username'    => $conn['username'],
                'avatar'      => $conn['avatar'],
                'seat_index'  => $seatIndex,
                'requester_fd' => $fd,
            ]));
        }
    }

    private static function handleSeatResponse(Server $server, int $fd, array $conn, array $data): void
    {
        $roomId    = $conn['room_id'];
        $userId    = (int) ($data['user_id'] ?? 0);
        $accepted  = (bool) ($data['accepted'] ?? false);
        $seatIndex = (int) ($data['seat_index'] ?? 0);

        if (! $roomId || ! $userId) {
            return;
        }

        $room = Room::find($roomId);

        if ($room?->host_user_id !== $conn['user_id']) {
            return;
        }

        $targetFd = static::getFdByUserId($userId);

        if ($accepted) {
            Redis::hset("room:{$roomId}:seats", $seatIndex, json_encode([
                'user_id'  => $userId,
                'username' => static::$connections[$targetFd]['username'] ?? '',
                'avatar'   => static::$connections[$targetFd]['avatar'] ?? '',
            ]));

            static::broadcastToRoom($server, $roomId, [
                'type'       => 'seat.assigned',
                'seat_index' => $seatIndex,
                'user_id'    => $userId,
                'username'   => static::$connections[$targetFd]['username'] ?? '',
                'avatar'     => static::$connections[$targetFd]['avatar'] ?? '',
            ]);
        }

        if ($targetFd && $server->isEstablished($targetFd)) {
            $server->push($targetFd, json_encode([
                'type'       => 'seat.response',
                'accepted'   => $accepted,
                'seat_index' => $accepted ? $seatIndex : -1,
            ]));
        }
    }

    private static function handleSeatLeave(Server $server, int $fd, array $conn, array $data): void
    {
        $roomId    = $conn['room_id'];
        $seatIndex = (int) ($data['seat_index'] ?? -1);

        if (! $roomId || $seatIndex < 0) {
            return;
        }

        Redis::hdel("room:{$roomId}:seats", $seatIndex);

        static::broadcastToRoom($server, $roomId, [
            'type'       => 'seat.vacated',
            'seat_index' => $seatIndex,
            'user_id'    => $conn['user_id'],
        ]);
    }

    // ── Gifts ─────────────────────────────────────────────────────────────────

    private static function handleGift(Server $server, int $fd, array $conn, array $data): void
    {
        $roomId       = $conn['room_id'];
        $giftId       = (int) ($data['gift_id'] ?? 0);
        $targetUserId = (int) ($data['target_user_id'] ?? 0);
        $quantity     = min(99, max(1, (int) ($data['quantity'] ?? 1)));

        if (! $roomId || ! $giftId) {
            return;
        }

        // Queue coin processing
        \App\Jobs\ProcessGiftJob::dispatch(
            senderId:    $conn['user_id'],
            receiverId:  $targetUserId,
            giftId:      $giftId,
            roomId:      $roomId,
            quantity:    $quantity,
        );

        // Immediately broadcast animation to room
        static::broadcastToRoom($server, $roomId, [
            'type'           => 'gift.animation',
            'gift_id'        => $giftId,
            'sender_id'      => $conn['user_id'],
            'sender_name'    => $conn['username'],
            'sender_avatar'  => $conn['avatar'],
            'target_user_id' => $targetUserId,
            'quantity'       => $quantity,
        ]);

        // Update PK score if active
        $pkSessionId = Redis::get("pk:room:{$roomId}");

        if ($pkSessionId) {
            $gift      = \App\Models\Gift::find($giftId);
            $coinValue = ($gift ? $gift->coin_price : 0) * $quantity;
            static::updatePkScore($server, $roomId, $pkSessionId, $coinValue);
        }
    }

    // ── PK Battle ─────────────────────────────────────────────────────────────

    private static function handlePkInvite(Server $server, int $fd, array $conn, array $data): void
    {
        $targetRoomId = $data['target_room_id'] ?? null;

        if (! $targetRoomId || ! $conn['room_id']) {
            return;
        }

        $pkSessionId = (string) Str::uuid();

        Redis::setex("pk:invite:{$pkSessionId}", 30, json_encode([
            'challenger_room' => $conn['room_id'],
            'target_room'     => $targetRoomId,
            'challenger_uid'  => $conn['user_id'],
            'challenger_name' => $conn['username'],
            'challenger_avatar' => $conn['avatar'],
        ]));

        $hostFd = static::getHostFd($targetRoomId);

        if ($hostFd && $server->isEstablished($hostFd)) {
            $server->push($hostFd, json_encode([
                'type'               => 'pk.invite',
                'pk_session_id'      => $pkSessionId,
                'challenger_room_id' => $conn['room_id'],
                'challenger_name'    => $conn['username'],
                'challenger_avatar'  => $conn['avatar'],
            ]));
        }
    }

    private static function handlePkResponse(Server $server, int $fd, array $conn, array $data): void
    {
        $pkSessionId = $data['pk_session_id'] ?? null;
        $accepted    = (bool) ($data['accepted'] ?? false);

        if (! $pkSessionId) {
            return;
        }

        $raw = Redis::get("pk:invite:{$pkSessionId}");
        Redis::del("pk:invite:{$pkSessionId}");

        if (! $raw) {
            return;
        }

        $invite = json_decode($raw, true);

        if (! $accepted) {
            $challengerFd = static::getFdByUserId($invite['challenger_uid']);

            if ($challengerFd && $server->isEstablished($challengerFd)) {
                $server->push($challengerFd, json_encode([
                    'type'          => 'pk.declined',
                    'pk_session_id' => $pkSessionId,
                ]));
            }

            return;
        }

        $duration = (int) \App\Models\Setting::get('pk_duration', 300);

        $sessionData = [
            'challenger_room' => $invite['challenger_room'],
            'target_room'     => $conn['room_id'],
            'scores'          => [
                $invite['challenger_room'] => 0,
                $conn['room_id']           => 0,
            ],
            'started_at' => time(),
            'duration'   => $duration,
        ];

        Redis::setex("pk:session:{$pkSessionId}", $duration + 60, json_encode($sessionData));
        Redis::setex("pk:room:{$invite['challenger_room']}", $duration + 60, $pkSessionId);
        Redis::setex("pk:room:{$conn['room_id']}", $duration + 60, $pkSessionId);

        $broadcast = [
            'type'            => 'pk.started',
            'pk_session_id'   => $pkSessionId,
            'challenger_room' => $invite['challenger_room'],
            'target_room'     => $conn['room_id'],
            'duration'        => $duration,
        ];

        static::broadcastToRoom($server, $invite['challenger_room'], $broadcast);
        static::broadcastToRoom($server, $conn['room_id'], $broadcast);

        // Schedule end job
        \App\Jobs\EndPkSessionJob::dispatch($pkSessionId)->delay(now()->addSeconds($duration));
    }

    private static function updatePkScore(Server $server, string $roomId, string $pkSessionId, int $coinValue): void
    {
        $raw = Redis::get("pk:session:{$pkSessionId}");

        if (! $raw) {
            return;
        }

        $session                        = json_decode($raw, true);
        $session['scores'][$roomId]     = ($session['scores'][$roomId] ?? 0) + $coinValue;
        $ttl                            = max(1, ($session['duration'] ?? 300) - (time() - $session['started_at']));

        Redis::setex("pk:session:{$pkSessionId}", $ttl + 60, json_encode($session));

        $scoreBroadcast = [
            'type'          => 'pk.scores',
            'pk_session_id' => $pkSessionId,
            'scores'        => $session['scores'],
        ];

        static::broadcastToRoom($server, $session['challenger_room'], $scoreBroadcast);
        static::broadcastToRoom($server, $session['target_room'], $scoreBroadcast);
    }

    // ── Game Events ───────────────────────────────────────────────────────────

    private static function handleGameEvent(Server $server, int $fd, array $conn, array $data): void
    {
        $roomId = $conn['room_id'];

        if (! $roomId) {
            return;
        }

        static::broadcastToRoom($server, $roomId, [
            'type'       => 'game.event',
            'user_id'    => $conn['user_id'],
            'event_data' => $data['event_data'] ?? [],
        ], exclude: $fd);
    }

    // ── Moderation ────────────────────────────────────────────────────────────

    private static function handleKick(Server $server, int $fd, array $conn, array $data): void
    {
        $roomId       = $conn['room_id'];
        $targetUserId = (int) ($data['user_id'] ?? 0);

        if (! $roomId || ! $targetUserId) {
            return;
        }

        $room = Room::find($roomId);

        if ($room?->host_user_id !== $conn['user_id']) {
            return;
        }

        $targetFd = static::getFdByUserId($targetUserId);

        if ($targetFd && $server->isEstablished($targetFd)) {
            $server->push($targetFd, json_encode(['type' => 'mod.kicked', 'room_id' => $roomId]));
            $server->close($targetFd);
        }

        static::broadcastToRoom($server, $roomId, [
            'type'    => 'mod.user_kicked',
            'user_id' => $targetUserId,
        ]);
    }

    private static function handleSilence(Server $server, int $fd, array $conn, array $data): void
    {
        $roomId       = $conn['room_id'];
        $targetUserId = (int) ($data['user_id'] ?? 0);
        $duration     = min(3600, max(60, (int) ($data['duration'] ?? 300)));

        if (! $roomId || ! $targetUserId) {
            return;
        }

        $room = Room::find($roomId);

        if ($room?->host_user_id !== $conn['user_id']) {
            return;
        }

        Redis::setex("silence:{$targetUserId}:{$roomId}", $duration, 1);

        $targetFd = static::getFdByUserId($targetUserId);

        if ($targetFd && $server->isEstablished($targetFd)) {
            $server->push($targetFd, json_encode([
                'type'     => 'mod.silenced',
                'duration' => $duration,
            ]));
        }

        static::broadcastToRoom($server, $roomId, [
            'type'     => 'mod.user_silenced',
            'user_id'  => $targetUserId,
            'duration' => $duration,
        ], exclude: $fd);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private static function broadcastToRoom(
        Server $server,
        string $roomId,
        array  $payload,
        int    $exclude = -1
    ): void {
        $fds  = Redis::smembers("room:{$roomId}:fds");
        $json = json_encode($payload);

        foreach ($fds as $fd) {
            $fd = (int) $fd;

            if ($fd === $exclude) {
                continue;
            }

            if ($server->isEstablished($fd)) {
                $server->push($fd, $json);
            } else {
                Redis::srem("room:{$roomId}:fds", $fd);
            }
        }
    }

    private static function getHostFd(string $roomId): ?int
    {
        $hostUserId = Room::where('id', $roomId)->value('host_user_id');

        if (! $hostUserId) {
            return null;
        }

        return static::getFdByUserId($hostUserId);
    }

    private static function getFdByUserId(int $userId): ?int
    {
        foreach (static::$connections as $fd => $conn) {
            if ($conn['user_id'] === $userId) {
                return (int) $fd;
            }
        }

        return null;
    }
}
