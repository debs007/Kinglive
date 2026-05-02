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
 * Fixed issues:
 * 1. Host room-end on any tab close — now checks for other active host connections
 * 2. Stale seat cleanup in handleRoomJoin was removing valid seated users
 * 3. Seat request/response race conditions allowing duplicate/overwritten seats
 * 4. Host reconnect to 'ended' room causing stuck 'connecting' screen
 */
class WebSocketHandler
{
    /** fd => [user_id, room_id, username, avatar] */
    private static array $connections = [];

    public static function onOpen(Server $server, Request $request): void
    {
        $fd    = $request->fd;
        $token = $request->get['token'] ?? null;

        if (! $token) {
            $server->push($fd, json_encode(['type' => 'error', 'message' => 'Missing token.']));
            $server->close($fd);
            return;
        }

        try {
            $user = JWTAuth::setToken($token)->authenticate();
        } catch (\Throwable $e) {
            Log::warning("WS auth failed fd={$fd}: " . $e->getMessage());
            $server->push($fd, json_encode(['type' => 'error', 'message' => 'Unauthorized.']));
            $server->close($fd);
            return;
        }

        if (! $user) {
            $server->close($fd);
            return;
        }

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

        // Clean stale fds for this user before adding new one
        $oldFds = Redis::smembers("ws:user:{$user->id}:fds");
        foreach ($oldFds as $oldFd) {
            $oldFd = (int) $oldFd;
            if (! $server->isEstablished($oldFd)) {
                Redis::srem("ws:user:{$user->id}:fds", $oldFd);
                Redis::del("ws:fd:{$oldFd}:user");
                if (isset(static::$connections[$oldFd]['room_id'])) {
                    $staleRoom = static::$connections[$oldFd]['room_id'];
                    Redis::srem("room:{$staleRoom}:fds", $oldFd);
                }
                unset(static::$connections[$oldFd]);
            }
        }
        //stale work done
        Redis::sadd("ws:user:{$user->id}:fds", $fd);
        Redis::expire("ws:user:{$user->id}:fds", 86400);
        Redis::setex("ws:fd:{$fd}:user", 86400, $user->id);

        $server->push($fd, json_encode(['type' => 'connected', 'user_id' => $user->id]));

        Log::info("WS: connected fd={$fd} user_id={$user->id}");
    }

    public static function onMessage(Server $server, Frame $frame): void
    {
        $fd = $frame->fd;
        if (! isset(static::$connections[$fd])) return;

        $conn = &static::$connections[$fd];
        $data = json_decode($frame->data, true);

        if (! $data || ! isset($data['type'])) return;

        try {
            match ($data['type']) {
                'room.join'           => static::handleRoomJoin($server, $fd, $conn, $data),
                'room.leave'          => static::handleRoomLeave($server, $fd, $conn),
                'chat.message'        => static::handleChat($server, $fd, $conn, $data),
                'seat.request'        => static::handleSeatRequest($server, $fd, $conn, $data),
                'seat.response'       => static::handleSeatResponse($server, $fd, $conn, $data),
                'seat.leave'          => static::handleSeatLeave($server, $fd, $conn, $data),
                'seat.lock'           => static::handleSeatLock($server, $fd, $conn, $data),
                'seat.deboard'        => static::handleSeatDeboard($server, $fd, $conn, $data),
                'mod.room_ban'        => static::handleRoomBan($server, $fd, $conn, $data),
                'gift.send'           => static::handleGift($server, $fd, $conn, $data),
                'pk.invite'           => static::handlePkInvite($server, $fd, $conn, $data),
                'pk.invite_user'      => static::handlePkInviteToUser($server, $fd, $conn, $data),
                'pk.invite_followers' => static::handlePkInviteFollowers($server, $fd, $conn, $data),
                'pk.response'         => static::handlePkResponse($server, $fd, $conn, $data),
                'game.event'          => static::handleGameEvent($server, $fd, $conn, $data),
                'mod.kick'            => static::handleKick($server, $fd, $conn, $data),
                'mod.silence'         => static::handleSilence($server, $fd, $conn, $data),
                'call.request'        => static::handleCallRequest($server, $fd, $conn, $data),
                'call.response'       => static::handleCallResponse($server, $fd, $conn, $data),
                'call.leave'          => static::handleCallLeave($server, $fd, $conn),
                'call.kick'           => static::handleCallKick($server, $fd, $conn, $data),
                'ping'                => static::handlePing($server, $fd, $conn),
                'room.bg_change'      => static::handleBgChange($server, $fd, $conn, $data),
                default               => null,
            };
        } catch (\Throwable $e) {
            Log::error("WS onMessage error fd={$fd} type={$data['type']}: " . $e->getMessage());
            if ($server->isEstablished($fd)) {
                $server->push($fd, json_encode([
                    'type'    => 'error',
                    'message' => 'Server error processing: ' . ($data['type'] ?? 'unknown'),
                ]));
            }
        }
    }

    public static function onClose(Server $server, int $fd): void
    {
        if (! isset(static::$connections[$fd])) return;

        $conn = static::$connections[$fd];

        if ($conn['room_id']) {
            $roomId = $conn['room_id'];
            $room   = \App\Models\Room::find($roomId);
            $isHost = $room && $room->host_user_id === $conn['user_id'];

            static::removeFromRoom($server, $fd, $conn);

            // FIX #1: Only end the room if the host has NO other active connections
            // in this room. Prevents stream death when host refreshes or closes a secondary tab.
            if ($isHost) {
                $hostStillConnected = false;

                // Check in-memory connections first (worker_num=1, this is primary truth)
                foreach (static::$connections as $otherFd => $otherConn) {
                    if ($otherFd !== $fd
                        && $otherConn['user_id'] === $conn['user_id']
                        && $otherConn['room_id'] === $roomId) {
                        $hostStillConnected = true;
                        break;
                    }
                }

                // Fallback: check Redis room FDs for any other FD belonging to host
                if (! $hostStillConnected) {
                    $roomFds = Redis::smembers("room:{$roomId}:fds");
                    foreach ($roomFds as $roomFd) {
                        $roomFd = (int) $roomFd;
                        if ($roomFd === $fd) continue;
                        $roomUserId = Redis::get("ws:fd:{$roomFd}:user");
                        if ((int) $roomUserId === $conn['user_id']) {
                            $hostStillConnected = true;
                            break;
                        }
                    }
                }

                if (! $hostStillConnected) {
                    $room?->update(['status' => 'ended', 'ended_at' => now()]);
                    static::broadcastToRoom($server, $roomId, [
                        'type'    => 'room.ended',
                        'room_id' => $roomId,
                    ]);
                    Redis::del(
                        "room:{$roomId}:fds",
                        "room:{$roomId}:viewers",
                        "room:{$roomId}:seats",
                        "room:{$roomId}:host_heartbeat",
                        "room:{$roomId}:heartbeats",
                    );
                    Log::info("WS: host ended room {$roomId} (no other connections)");
                } else {
                    Log::info("WS: host fd={$fd} closed but other connections remain in room {$roomId}, keeping room alive");
                }
            }
        }

        Redis::srem("ws:user:{$conn['user_id']}:fds", $fd);
        Redis::del("ws:fd:{$fd}:user");
        unset(static::$connections[$fd]);

        Log::info("WS: disconnected fd={$fd} user_id={$conn['user_id']}");
    }

    // ── Room ──────────────────────────────────────────────────────────────────

    private static function handleRoomJoin(Server $server, int $fd, array &$conn, array $data): void
    {
        $roomId = $data['room_id'] ?? null;
        if (! $roomId) return;

        $room = Room::find($roomId);
        if (! $room) {
            $server->push($fd, json_encode(['type' => 'error', 'message' => 'Room not found.']));
            return;
        }

        // FIX #4: Allow host to rejoin an ended room and auto-resurrect it.
        // Non-hosts are still blocked from joining ended rooms.
        if (! in_array($room->status, ['live', 'waiting'])) {
            if ($room->host_user_id !== $conn['user_id']) {
                $server->push($fd, json_encode(['type' => 'error', 'message' => 'Room has ended.']));
                return;
            }
            // Host joining an ended room — resurrect it
            $room->update(['status' => 'live', 'started_at' => now(), 'ended_at' => null]);
            Log::info("WS: host resurrected room {$roomId}");
        }

        $banService = app(BanService::class);
        if ($banService->isRoomBanned($conn['user_id'], $roomId)) {
            $server->push($fd, json_encode(['type' => 'room.banned', 'room_id' => $roomId, 'message' => 'You are banned from this room.']));
            return;
        }

        $conn['room_id'] = $roomId;

        // Clean dead FDs from room set before adding this one
        $existingFds = Redis::smembers("room:{$roomId}:fds");
        foreach ($existingFds as $existingFd) {
            $existingFd = (int) $existingFd;
            if ($existingFd === $fd) continue;
            $existingUser = Redis::get("ws:fd:{$existingFd}:user");
            // Remove dead FDs (no user mapping) OR old FDs for this same user
            if (! $existingUser || (int) $existingUser === $conn['user_id']) {
                Redis::srem("room:{$roomId}:fds", $existingFd);
            }
        }
        Redis::srem("room:{$roomId}:fds", $fd);
        Redis::sadd("room:{$roomId}:fds", $fd);
        Redis::expire("room:{$roomId}:fds", 86400);

        $room = Room::find($roomId);
        if ($room?->host_user_id !== $conn['user_id']) {
            Redis::incr("room:{$roomId}:viewers");
        }

        static::broadcastToRoom($server, $roomId, [
            'type'         => 'user.joined',
            'user_id'      => $conn['user_id'],
            'username'     => $conn['username'],
            'avatar'       => $conn['avatar'],
            'level'        => $conn['level'],
            'viewer_count' => (int) Redis::get("room:{$roomId}:viewers"),
        ], exclude: $fd);

        // FIX #2: Removed aggressive stale seat cleanup that was wiping valid seats.
        // onClose + removeFromRoom already handle seat cleanup when users disconnect.
        // Proactive cleanup here was causing seated users to vanish for new joiners
        // because getFdByUserId without $server could return a stale Redis fd.

        $chatRaw             = Redis::zrange("room:{$roomId}:chat", -50, -1);
        $chat                = array_map(fn ($c) => json_decode($c, true), $chatRaw);
        $callParticipantsRaw = Redis::hgetall("call:{$roomId}:participants") ?: [];
        $callParticipants    = array_values(array_map(fn ($p) => json_decode($p, true), $callParticipantsRaw));

        // Build seats array — decode JSON values for frontend consumption
        $rawSeats = Redis::hgetall("room:{$roomId}:seats") ?: [];
        $parsedSeats = [];
        foreach ($rawSeats as $idx => $json) {
            $parsed = json_decode($json, true);
            if (is_array($parsed)) {
                $parsedSeats[$idx] = $parsed;
            }
        }

        $room          = \App\Models\Room::find($roomId);
        $currentBgUrl  = $room?->current_bg_url;

        // FIX #4: Include room status and host_id so frontend knows stream is live
        $server->push($fd, json_encode([
            'type'              => 'room.state',
            'room_id'           => $roomId,
            'room_status'       => $room?->status ?? 'unknown',
            'host_id'           => $room?->host_user_id,
            'viewer_count'      => (int) Redis::get("room:{$roomId}:viewers"),
            'recent_chat'       => array_values($chat),
            'seats'             => $parsedSeats,
            'call_participants' => $callParticipants,
            'current_bg_url'    => $currentBgUrl,
        ]));

        // If host just joined/rejoined a live room, broadcast room.started so
        // any viewers stuck on 'connecting' know the stream is active.
        if ($room?->host_user_id === $conn['user_id'] && $room?->status === 'live') {
            static::broadcastToRoom($server, $roomId, [
                'type'    => 'room.started',
                'room_id' => $roomId,
                'host_id' => $conn['user_id'],
            ], exclude: $fd);
        }

        $pkSessionId = Redis::get("pk:room:{$roomId}");
        if ($pkSessionId) {
            $pkRaw = Redis::get("pk:session:{$pkSessionId}");
            if ($pkRaw) {
                $pkSession = json_decode($pkRaw, true);
                $server->push($fd, json_encode([
                    'type'            => 'pk.running',
                    'pk_session_id'   => $pkSessionId,
                    'challenger_room' => $pkSession['challenger_room'],
                    'target_room'     => $pkSession['target_room'],
                    'scores'          => $pkSession['scores'],
                    'started_at'      => $pkSession['started_at'],
                    'duration'        => $pkSession['duration'],
                    'pk_channel_id'   => $pkSession['pk_channel_id'] ?? null,
                    'challenger_uid'  => $pkSession['challenger_uid'] ?? null,
                    'target_uid'      => $pkSession['target_uid'] ?? null,
                ]));
            }
        }
    }

    private static function handleRoomLeave(Server $server, int $fd, array &$conn): void
    {
        if ($conn['room_id']) static::removeFromRoom($server, $fd, $conn);
    }

    private static function removeFromRoom(Server $server, int $fd, array &$conn): void
    {
        $roomId = $conn['room_id'];
        if (! $roomId) return;

        Redis::srem("room:{$roomId}:fds", $fd);

        $room = Room::find($roomId);
        if ($room?->host_user_id !== $conn['user_id']) {
            $remaining = (int) Redis::decr("room:{$roomId}:viewers");
            if ($remaining < 0) Redis::set("room:{$roomId}:viewers", 0);
        }

        Redis::del("room:{$roomId}:user_pending:{$conn['user_id']}");

        // Remove user from any seat they occupy
        $seats = Redis::hgetall("room:{$roomId}:seats") ?: [];
        foreach ($seats as $seatIndex => $seatJson) {
            $seat = json_decode($seatJson, true);
            if (($seat['user_id'] ?? null) == $conn['user_id']) {
                Redis::hdel("room:{$roomId}:seats", $seatIndex);
                static::broadcastToRoom($server, $roomId, ['type' => 'seat.vacated', 'seat_index' => (int) $seatIndex]);
                break;
            }
        }

        static::broadcastToRoom($server, $roomId, [
            'type'         => 'user.left',
            'user_id'      => $conn['user_id'],
            'username'     => $conn['username'],
            'viewer_count' => (int) (Redis::get("room:{$roomId}:viewers") ?? 0),
        ]);

        $conn['room_id'] = null;
    }

    // ── Chat ──────────────────────────────────────────────────────────────────

    private static function handleChat(Server $server, int $fd, array $conn, array $data): void
    {
        $roomId = $conn['room_id'];
        if (! $roomId) return;

        if (Redis::exists("silence:{$conn['user_id']}:{$roomId}")) {
            $server->push($fd, json_encode(['type' => 'error', 'message' => 'You are silenced in this room.']));
            return;
        }

        $message = htmlspecialchars(mb_substr($data['message'] ?? '', 0, 500));
        if ($message === '') return;

        $payload = json_encode([
            'user_id'  => $conn['user_id'],
            'username' => $conn['username'],
            'avatar'   => $conn['avatar'],
            'level'    => $conn['level'],
            'message'  => $message,
            'ts'       => time(),
        ]);

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
        ], exclude: $fd);
    }

    // ── DM (Direct Message) ───────────────────────────────────────────────────

    /**
     * Queue a DM message for delivery to a user via WebSocket.
     * Called from HTTP process (DirectMessageController) — cannot access
     * WS $connections directly. Stores in Redis, delivered on next ping.
     */
    public static function queueDmForUser(int $receiverId, array $message): void
    {
        $payload = json_encode([
            'type'    => 'dm.message',
            'message' => $message,
        ]);

        $key = "ws:user:{$receiverId}:dm_pending";
        Redis::rpush($key, $payload);
        Redis::expire($key, 300);
    }

    // ── Seats ─────────────────────────────────────────────────────────────────

    private static function handleSeatRequest(Server $server, int $fd, array $conn, array $data): void
    {
        $roomId    = $conn['room_id'];
        $seatIndex = (int) ($data['seat_index'] ?? -1);
        if (! $roomId || $seatIndex < 0) return;

        // FIX #3: Prevent user from requesting a seat if they are already seated
        $allSeats = Redis::hgetall("room:{$roomId}:seats") ?: [];
        foreach ($allSeats as $idx => $seatJson) {
            $seat = json_decode($seatJson, true);
            if (($seat['user_id'] ?? null) == $conn['user_id']) {
                $server->push($fd, json_encode([
                    'type'       => 'seat.error',
                    'message'    => 'You are already on a seat. Leave your current seat first.',
                    'seat_index' => (int) $idx,
                ]));
                return;
            }
        }

        $userPendingKey = "room:{$roomId}:user_pending:{$conn['user_id']}";
        if (Redis::get($userPendingKey) !== null) {
            $server->push($fd, json_encode(['type' => 'seat.error', 'message' => 'You already have a pending seat request.']));
            return;
        }

        $existing = Redis::hget("room:{$roomId}:seats", $seatIndex);
        if ($existing) {
            $seat = json_decode($existing, true);
            if (isset($seat['user_id']) || ($seat['is_locked'] ?? false)) {
                $server->push($fd, json_encode(['type' => 'seat.rejected', 'seat_index' => $seatIndex]));
                return;
            }
        }

        $pendingKey = "room:{$roomId}:seat_request:{$seatIndex}";
        $prevUserId = Redis::get($pendingKey);
        if ($prevUserId && (int) $prevUserId !== $conn['user_id']) {
            $prevFd = static::getFdByUserId((int) $prevUserId, $server);
            if ($prevFd && $server->isEstablished($prevFd)) {
                $server->push($prevFd, json_encode(['type' => 'seat.response', 'accepted' => false, 'seat_index' => $seatIndex]));
            }
        }

        Redis::setex($pendingKey, 60, $conn['user_id']);
        Redis::setex($userPendingKey, 60, $seatIndex);

        $hostFd = static::getHostFd($roomId, $server);
        if ($hostFd && $server->isEstablished($hostFd)) {
            $server->push($hostFd, json_encode([
                'type'       => 'seat.request',
                'user_id'    => $conn['user_id'],
                'username'   => $conn['username'],
                'avatar'     => $conn['avatar'],
                'seat_index' => $seatIndex,
            ]));
        }
    }

    private static function handleSeatResponse(Server $server, int $fd, array $conn, array $data): void
    {
        $roomId    = $conn['room_id'];
        $userId    = (int) ($data['user_id'] ?? 0);
        $accepted  = (bool) ($data['accepted'] ?? false);
        $seatIndex = (int) ($data['seat_index'] ?? 0);

        if (! $roomId || ! $userId) return;

        $room = Room::find($roomId);
        if ($room?->host_user_id !== $conn['user_id']) return;

        $targetFd = static::getFdByUserId($userId, $server);

        Redis::del("room:{$roomId}:seat_request:{$seatIndex}");
        Redis::del("room:{$roomId}:user_pending:{$userId}");

        if ($accepted) {
            // FIX #3: Verify the target user is still connected
            if (! $targetFd || ! $server->isEstablished($targetFd)) {
                $server->push($fd, json_encode([
                    'type'    => 'seat.error',
                    'message' => 'User is no longer connected.',
                ]));
                return;
            }

            // FIX #3: Double-check the seat is still vacant before assigning.
            // Another response or a lock could have filled it since the request was made.
            $currentSeatRaw = Redis::hget("room:{$roomId}:seats", $seatIndex);
            if ($currentSeatRaw) {
                $currentSeat = json_decode($currentSeatRaw, true);
                if (isset($currentSeat['user_id']) && (int) $currentSeat['user_id'] !== $userId) {
                    // Seat was taken by someone else — reject
                    if ($targetFd && $server->isEstablished($targetFd)) {
                        $server->push($targetFd, json_encode([
                            'type'       => 'seat.response',
                            'accepted'   => false,
                            'seat_index' => -1,
                            'message'    => 'Seat was taken by another user.',
                        ]));
                    }
                    return;
                }
            }

            // FIX #3: Also ensure the target user didn't end up in another seat somehow
            foreach (Redis::hgetall("room:{$roomId}:seats") ?: [] as $idx => $seatJson) {
                $seat = json_decode($seatJson, true);
                if (($seat['user_id'] ?? null) == $userId && (int) $idx !== $seatIndex) {
                    // User already in another seat — clean it up first
                    Redis::hdel("room:{$roomId}:seats", $idx);
                    static::broadcastToRoom($server, $roomId, ['type' => 'seat.vacated', 'seat_index' => (int) $idx]);
                }
            }

            $agoraUid = (int) $userId;

            Redis::hset("room:{$roomId}:seats", $seatIndex, json_encode([
                'user_id'   => $userId,
                'username'  => static::$connections[$targetFd]['username'] ?? '',
                'avatar'    => static::$connections[$targetFd]['avatar'] ?? '',
                'agora_uid' => $agoraUid,
            ]));

            static::broadcastToRoom($server, $roomId, [
                'type'       => 'seat.assigned',
                'seat_index' => $seatIndex,
                'user_id'    => $userId,
                'username'   => static::$connections[$targetFd]['username'] ?? '',
                'avatar'     => static::$connections[$targetFd]['avatar'] ?? '',
                'agora_uid'  => $agoraUid,
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
        if (! $roomId || $seatIndex < 0) return;

        Redis::del("room:{$roomId}:user_pending:{$conn['user_id']}");
        Redis::del("room:{$roomId}:seat_request:{$seatIndex}");
        Redis::hdel("room:{$roomId}:seats", $seatIndex);

        static::broadcastToRoom($server, $roomId, [
            'type'       => 'seat.vacated',
            'seat_index' => $seatIndex,
            'user_id'    => $conn['user_id'],
        ]);
    }

    private static function handleSeatLock(Server $server, int $fd, array $conn, array $data): void
    {
        $roomId    = $conn['room_id'];
        $seatIndex = (int) ($data['seat_index'] ?? -1);
        $lock      = (bool) ($data['lock'] ?? true);

        if (! $roomId || $seatIndex < 0) return;

        $room = Room::find($roomId);
        if ($room?->host_user_id !== $conn['user_id']) return;

        $existing = Redis::hget("room:{$roomId}:seats", $seatIndex);
        $seat     = $existing ? json_decode($existing, true) : [];
        if (isset($seat['user_id'])) return;

        if ($lock) {
            Redis::hset("room:{$roomId}:seats", $seatIndex, json_encode(['is_locked' => true]));
        } else {
            Redis::hdel("room:{$roomId}:seats", $seatIndex);
        }

        static::broadcastToRoom($server, $roomId, [
            'type'       => 'seat.lock_changed',
            'seat_index' => $seatIndex,
            'is_locked'  => $lock,
        ]);
    }

    private static function handleSeatDeboard(Server $server, int $fd, array $conn, array $data): void
    {
        $roomId    = $conn['room_id'];
        $seatIndex = (int) ($data['seat_index'] ?? -1);
        if (! $roomId || $seatIndex < 0) return;

        $room = Room::find($roomId);
        if ($room?->host_user_id !== $conn['user_id']) return;

        $existing = Redis::hget("room:{$roomId}:seats", $seatIndex);
        if (! $existing) return;

        $seat   = json_decode($existing, true);
        $userId = $seat['user_id'] ?? null;
        if (! $userId) return;

        Redis::hdel("room:{$roomId}:seats", $seatIndex);

        $targetFd = static::getFdByUserId((int) $userId, $server);
        if ($targetFd && $server->isEstablished($targetFd)) {
            $server->push($targetFd, json_encode(['type' => 'seat.deboarded', 'seat_index' => $seatIndex]));
            $server->push($targetFd, json_encode(['type' => 'agora.demote']));
        }

        static::broadcastToRoom($server, $roomId, ['type' => 'seat.vacated', 'seat_index' => $seatIndex]);
    }

    // ── Room Ban ──────────────────────────────────────────────────────────────

    private static function handleRoomBan(Server $server, int $fd, array $conn, array $data): void
    {
        $roomId       = $conn['room_id'];
        $targetUserId = (int) ($data['user_id'] ?? 0);
        if (! $roomId || ! $targetUserId) return;

        $room = Room::find($roomId);
        if ($room?->host_user_id !== $conn['user_id']) return;

        $banService = app(\App\Services\BanService::class);
        $banService->ban(
            targetUserId: $targetUserId,
            adminId:      $conn['user_id'],
            reason:       'Banned from room by host',
            duration:     'permanent',
            type:         'room',
            roomId:       $roomId,
        );

        $targetFd = static::getFdByUserId($targetUserId, $server);
        if ($targetFd && $server->isEstablished($targetFd)) {
            $server->push($targetFd, json_encode(['type' => 'room.banned', 'room_id' => $roomId, 'message' => 'You have been banned from this room.']));
            $server->close($targetFd);
        }

        static::broadcastToRoom($server, $roomId, ['type' => 'mod.user_kicked', 'user_id' => $targetUserId]);
    }

    // ── Gifts ─────────────────────────────────────────────────────────────────

    private static function handleGift(Server $server, int $fd, array $conn, array $data): void
    {
        $roomId       = $conn['room_id'];
        $giftId       = (int) ($data['gift_id'] ?? 0);
        $targetUserId = (int) ($data['target_user_id'] ?? 0);
        $quantity     = min(99, max(1, (int) ($data['quantity'] ?? 1)));

        if (! $roomId || ! $giftId) return;

        $gift      = \App\Models\Gift::find($giftId);
        $coinValue = ($gift ? $gift->coin_price : 0) * $quantity;

        if (! $gift) {
            $server->push($fd, json_encode(['type' => 'gift.error', 'message' => 'Gift not found.']));
            return;
        }

        $sender = \App\Models\User::find($conn['user_id']);
        if (! $sender || $sender->coin_balance < $coinValue) {
            $server->push($fd, json_encode([
                'type'     => 'gift.error',
                'message'  => 'Not enough coins to send this gift.',
                'required' => $coinValue,
                'balance'  => $sender?->coin_balance ?? 0,
            ]));
            return;
        }

        \App\Jobs\ProcessGiftJob::dispatch(
            senderId:   $conn['user_id'],
            receiverId: $targetUserId,
            giftId:     $giftId,
            roomId:     $roomId,
            quantity:   $quantity,
        );

        $newBalance = max(0, $sender->coin_balance - $coinValue);
        $server->push($fd, json_encode(['type' => 'balance.update', 'coin_balance' => $newBalance]));

        $totalDiamonds      = ($gift->diamond_value ?? 0) * $quantity;
        $room               = \App\Models\Room::find($roomId);
        $hostDiamondBalance = $room
            ? (\App\Models\User::find($room->host_user_id)?->diamond_balance ?? 0) + $totalDiamonds
            : 0;

        static::broadcastToRoom($server, $roomId, [
            'type'            => 'diamond.update',
            'diamond_balance' => $hostDiamondBalance,
            'diamonds_earned' => $totalDiamonds,
        ]);

        static::broadcastToRoom($server, $roomId, [
            'type'           => 'gift.animation',
            'gift_id'        => $giftId,
            'gift_name'      => $gift->name ?? '',
            'svga_url'       => $gift->svga_url ?? '',
            'gift_thumbnail' => $gift->thumbnail_url ?? '',
            'sender_id'      => $conn['user_id'],
            'sender_name'    => $conn['username'],
            'sender_avatar'  => $conn['avatar'],
            'target_user_id' => $targetUserId,
            'quantity'       => $quantity,
            'coins'          => $coinValue,
        ]);

        $pkSessionId = Redis::get("pk:room:{$roomId}");
        if ($pkSessionId) static::updatePkScore($server, $roomId, $pkSessionId, $coinValue);
    }

    // ── PK ────────────────────────────────────────────────────────────────────

    private static function handlePkInvite(Server $server, int $fd, array $conn, array $data): void
    {
        $targetRoomId = $data['target_room_id'] ?? null;
        if (! $targetRoomId || ! $conn['room_id']) return;

        $pkSessionId = (string) Str::uuid();

        Redis::setex("pk:invite:{$pkSessionId}", 30, json_encode([
            'challenger_room'   => $conn['room_id'],
            'target_room'       => $targetRoomId,
            'challenger_uid'    => $conn['user_id'],
            'challenger_name'   => $conn['username'],
            'challenger_avatar' => $conn['avatar'],
        ]));

        $hostFd = static::getHostFd($targetRoomId, $server);
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
        if (! $pkSessionId) return;

        $raw = Redis::get("pk:invite:{$pkSessionId}");
        if (! $raw) {
            if ($accepted) $server->push($fd, json_encode(['type' => 'pk.full', 'message' => 'PK battle already started.']));
            return;
        }

        $invite = json_decode($raw, true);

        if (! $accepted) {
            $challengerFd = static::getFdByUserId($invite['challenger_uid'], $server);
            if ($challengerFd && $server->isEstablished($challengerFd)) {
                $server->push($challengerFd, json_encode(['type' => 'pk.declined', 'pk_session_id' => $pkSessionId]));
            }
            return;
        }

        $claimed = Redis::del("pk:invite:{$pkSessionId}");
        if ($claimed === 0) {
            $server->push($fd, json_encode(['type' => 'pk.full', 'message' => 'PK battle already started.']));
            return;
        }

        $duration    = (int) \App\Models\Setting::get('pk_duration', 300);
        $pkChannelId = 'pk_' . $pkSessionId;
        $agoraService = app(\App\Services\AgoraService::class);
        $challengerAgoraUid = $invite['challenger_uid'];
        $targetAgoraUid     = $conn['user_id'];
        $challengerToken    = $agoraService->generateToken($pkChannelId, $challengerAgoraUid);
        $targetToken        = $agoraService->generateToken($pkChannelId, $targetAgoraUid);

        $sessionData = [
            'challenger_room'   => $invite['challenger_room'],
            'target_room'       => $conn['room_id'],
            'scores'            => [$invite['challenger_room'] => 0, $conn['room_id'] => 0],
            'started_at'        => now()->toISOString(),
            'duration'          => $duration,
            'pk_channel_id'     => $pkChannelId,
            'challenger_uid'    => $challengerAgoraUid,
            'target_uid'        => $targetAgoraUid,
            'challenger_token'  => $challengerToken,
            'target_token'      => $targetToken,
        ];

        Redis::setex("pk:session:{$pkSessionId}", $duration + 60, json_encode($sessionData));
        Redis::setex("pk:room:{$invite['challenger_room']}", $duration + 60, $pkSessionId);
        Redis::setex("pk:room:{$conn['room_id']}", $duration + 60, $pkSessionId);

        $broadcast = [
            'type'                 => 'pk.started',
            'pk_session_id'        => $pkSessionId,
            'challenger_room'      => $invite['challenger_room'],
            'target_room'          => $conn['room_id'],
            'duration'             => $duration,
            'started_at'           => now()->toISOString(),
            'pk_channel_id'        => $pkChannelId,
            'challenger_uid'       => $challengerAgoraUid,
            'challenger_name'      => $invite['challenger_name'],
            'challenger_avatar'    => $invite['challenger_avatar'] ?? '',
            'challenger_agora_uid' => $challengerAgoraUid,
            'target_uid'           => $targetAgoraUid,
            'target_name'          => $conn['username'],
            'target_avatar'        => $conn['avatar'] ?? '',
            'target_agora_uid'     => $targetAgoraUid,
        ];

        $challengerHostFd = static::getFdByUserId($challengerAgoraUid, $server);
        if ($challengerHostFd && $server->isEstablished($challengerHostFd)) {
            $server->push($challengerHostFd, json_encode([
                'type'            => 'pk.join_channel',
                'pk_channel_id'   => $pkChannelId,
                'agora_token'     => $challengerToken,
                'agora_uid'       => $challengerAgoraUid,
                'opponent_uid'    => $targetAgoraUid,
                'opponent_name'   => $conn['username'],
                'opponent_avatar' => $conn['avatar'] ?? '',
            ]));
        }

        $server->push($fd, json_encode([
            'type'            => 'pk.join_channel',
            'pk_channel_id'   => $pkChannelId,
            'agora_token'     => $targetToken,
            'agora_uid'       => $targetAgoraUid,
            'opponent_uid'    => $challengerAgoraUid,
            'opponent_name'   => $invite['challenger_name'],
            'opponent_avatar' => $invite['challenger_avatar'] ?? '',
        ]));

        static::broadcastToRoom($server, $invite['challenger_room'], $broadcast);
        static::broadcastToRoom($server, $conn['room_id'], $broadcast);

        \App\Jobs\EndPkSessionJob::dispatch($pkSessionId)->delay(now()->addSeconds($duration));
    }

    private static function updatePkScore(Server $server, string $roomId, string $pkSessionId, int $coinValue): void
    {
        $raw = Redis::get("pk:session:{$pkSessionId}");
        if (! $raw) return;

        $session                    = json_decode($raw, true);
        $session['scores'][$roomId] = ($session['scores'][$roomId] ?? 0) + $coinValue;
        $ttl = Redis::ttl("pk:session:{$pkSessionId}");
        if ($ttl <= 0) $ttl = 360;
        Redis::setex("pk:session:{$pkSessionId}", $ttl, json_encode($session));

        $scoreBroadcast = ['type' => 'pk.scores', 'pk_session_id' => $pkSessionId, 'scores' => $session['scores']];
        static::broadcastToRoom($server, $session['challenger_room'], $scoreBroadcast);
        static::broadcastToRoom($server, $session['target_room'], $scoreBroadcast);
    }

    // ── Game Events ───────────────────────────────────────────────────────────

    private static function handleGameEvent(Server $server, int $fd, array $conn, array $data): void
    {
        $roomId = $conn['room_id'];
        if (! $roomId) return;

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
        if (! $roomId || ! $targetUserId) return;

        $room = Room::find($roomId);
        if ($room?->host_user_id !== $conn['user_id']) return;

        $targetFd = static::getFdByUserId($targetUserId, $server);
        if ($targetFd && $server->isEstablished($targetFd)) {
            $server->push($targetFd, json_encode(['type' => 'mod.kicked', 'room_id' => $roomId]));
            $server->close($targetFd);
        }

        static::broadcastToRoom($server, $roomId, ['type' => 'mod.user_kicked', 'user_id' => $targetUserId]);
    }

    private static function handleSilence(Server $server, int $fd, array $conn, array $data): void
    {
        $roomId       = $conn['room_id'];
        $targetUserId = (int) ($data['user_id'] ?? 0);
        $duration     = min(3600, max(60, (int) ($data['duration'] ?? 300)));
        if (! $roomId || ! $targetUserId) return;

        $room = Room::find($roomId);
        if ($room?->host_user_id !== $conn['user_id']) return;

        Redis::setex("silence:{$targetUserId}:{$roomId}", $duration, 1);

        $targetFd = static::getFdByUserId($targetUserId, $server);
        if ($targetFd && $server->isEstablished($targetFd)) {
            $server->push($targetFd, json_encode(['type' => 'mod.silenced', 'duration' => $duration]));
        }

        static::broadcastToRoom($server, $roomId, [
            'type'     => 'mod.user_silenced',
            'user_id'  => $targetUserId,
            'duration' => $duration,
        ], exclude: $fd);
    }

    // ── Ping ──────────────────────────────────────────────────────────────────

    private static function handlePing(Server $server, int $fd, array $conn): void
    {
        $server->push($fd, json_encode(['type' => 'pong']));
        static::flushPendingBroadcasts($server);

        // Deliver pending DM messages for this user (cross-worker delivery)
        $dmKey = "ws:user:{$conn['user_id']}:dm_pending";
        while ($pending = Redis::lpop($dmKey)) {
            $server->push($fd, $pending);
        }

        $roomId = $conn['room_id'] ?? null;
        if (! $roomId) return;

        $pendingKey = "room:{$roomId}:pending_broadcast";
        $pending    = Redis::get($pendingKey);
        if ($pending) $server->push($fd, $pending);
    }

    private static function flushPendingBroadcasts(Server $server): void
    {
        $raw = Redis::rpop("ws:pending_broadcasts");
        if (! $raw) return;

        $item = json_decode($raw, true);
        if (! $item || time() > ($item['expires'] ?? 0)) return;

        foreach ($item['rooms'] as $roomId) {
            static::broadcastToRoom($server, $roomId, json_decode($item['payload'], true));
        }
    }

    // ── PK Invite to User/Followers ───────────────────────────────────────────

    private static function handlePkInviteToUser(Server $server, int $fd, array $conn, array $data): void
    {
        $targetUserId = (int) ($data['target_user_id'] ?? 0);
        if (! $targetUserId || ! $conn['room_id']) return;

        $pkSessionId = (string) \Illuminate\Support\Str::uuid();
        Redis::setex("pk:invite:{$pkSessionId}", 60, json_encode([
            'challenger_room'   => $conn['room_id'],
            'challenger_uid'    => $conn['user_id'],
            'challenger_name'   => $conn['username'],
            'challenger_avatar' => $conn['avatar'],
            'target_user_id'    => $targetUserId,
            'sent_at'           => time(),
        ]));

        $payload = [
            'type'               => 'pk.invite',
            'pk_session_id'      => $pkSessionId,
            'challenger_room_id' => $conn['room_id'],
            'challenger_name'    => $conn['username'],
            'challenger_avatar'  => $conn['avatar'],
        ];

        $targetFd = static::getFdByUserId($targetUserId, $server);
        if ($targetFd && $server->isEstablished($targetFd)) {
            $server->push($targetFd, json_encode($payload));
        } else {
            static::storeNotification($targetUserId, 'pk_invite', array_merge($payload, ['message' => "{$conn['username']} challenged you to a PK battle!"]));
        }
    }

    private static function handlePkInviteFollowers(Server $server, int $fd, array $conn, array $data): void
    {
        if (! $conn['room_id']) return;

        $pkSessionId = (string) \Illuminate\Support\Str::uuid();
        Redis::setex("pk:invite:{$pkSessionId}", 60, json_encode([
            'challenger_room'   => $conn['room_id'],
            'challenger_uid'    => $conn['user_id'],
            'challenger_name'   => $conn['username'],
            'challenger_avatar' => $conn['avatar'],
            'sent_at'           => time(),
        ]));

        $followerIds = \App\Models\User::find($conn['user_id'])?->followers()->pluck('follower_id')->toArray() ?? [];
        if (empty($followerIds)) return;

        $payload = [
            'type'               => 'pk.invite',
            'pk_session_id'      => $pkSessionId,
            'challenger_room_id' => $conn['room_id'],
            'challenger_name'    => $conn['username'],
            'challenger_avatar'  => $conn['avatar'],
        ];

        foreach ($followerIds as $followerId) {
            $targetFd = static::getFdByUserId($followerId, $server);
            if ($targetFd && $server->isEstablished($targetFd)) {
                $server->push($targetFd, json_encode($payload));
            } else {
                static::storeNotification((int) $followerId, 'pk_invite', array_merge($payload, ['message' => "{$conn['username']} challenged you to a PK battle!"]));
            }
        }
    }

    private static function storeNotification(int $userId, string $type, array $data): void
    {
        try {
            \App\Models\Notification::create(['user_id' => $userId, 'type' => $type, 'data' => $data]);
            $notifService = app(\App\Services\NotificationService::class);
            $title = $type === 'pk_invite' ? '⚔ PK Battle Challenge' : 'New Notification';
            $body  = $data['message'] ?? 'You have a new notification';
            $notifService->sendToUser($userId, $title, $body, array_merge($data, ['type' => $type]));
        } catch (\Throwable $e) {
            Log::error("WS storeNotification failed: " . $e->getMessage());
        }
    }

    // ── Video Call ────────────────────────────────────────────────────────────

    private static function handleCallRequest(Server $server, int $fd, array $conn, array $data): void
    {
        $roomId = $conn['room_id'];
        if (! $roomId) return;

        if (Redis::get("pk:room:{$roomId}")) {
            $server->push($fd, json_encode(['type' => 'call.full', 'message' => 'PK battle is in progress.']));
            return;
        }

        if (Redis::hlen("call:{$roomId}:participants") >= 3) {
            $server->push($fd, json_encode(['type' => 'call.full']));
            return;
        }

        $hostFd = static::getHostFd($roomId, $server);
        if ($hostFd && $server->isEstablished($hostFd)) {
            $server->push($hostFd, json_encode([
                'type'     => 'call.request',
                'user_id'  => $conn['user_id'],
                'username' => $conn['username'],
                'avatar'   => $conn['avatar'],
            ]));
        }
    }

    private static function handleCallResponse(Server $server, int $fd, array $conn, array $data): void
    {
        $roomId   = $conn['room_id'];
        $userId   = (int) ($data['user_id'] ?? 0);
        $accepted = (bool) ($data['accepted'] ?? false);
        if (! $roomId || ! $userId) return;

        $room = Room::find($roomId);
        if ($room?->host_user_id !== $conn['user_id']) return;

        $targetFd = static::getFdByUserId($userId, $server);
        if (! $targetFd || ! $server->isEstablished($targetFd)) return;

        if ($accepted) {
            $callKey    = "call:{$roomId}:participants";
            $agoraUid   = $userId;
            $targetConn = static::$connections[$targetFd] ?? [];

            Redis::hset($callKey, $userId, json_encode([
                'user_id'    => $userId,
                'username'   => $targetConn['username'] ?? '',
                'avatar'     => $targetConn['avatar']   ?? '',
                'agora_uid'  => $agoraUid,
                'camera_off' => true,
            ]));
            Redis::expire($callKey, 86400);

            $server->push($targetFd, json_encode(['type' => 'call.accepted', 'agora_uid' => $agoraUid]));

            static::broadcastToRoom($server, $roomId, [
                'type'       => 'call.joined',
                'user_id'    => $userId,
                'username'   => $targetConn['username'] ?? '',
                'avatar'     => $targetConn['avatar']   ?? '',
                'agora_uid'  => $agoraUid,
                'camera_off' => true,
            ]);
        } else {
            $server->push($targetFd, json_encode(['type' => 'call.rejected']));
        }
    }

    private static function handleCallLeave(Server $server, int $fd, array $conn): void
    {
        $roomId = $conn['room_id'];
        if (! $roomId) return;

        Redis::hdel("call:{$roomId}:participants", $conn['user_id']);
        static::broadcastToRoom($server, $roomId, ['type' => 'call.left', 'user_id' => $conn['user_id']]);
    }

    private static function handleCallKick(Server $server, int $fd, array $conn, array $data): void
    {
        $roomId = $conn['room_id'];
        $userId = (int) ($data['user_id'] ?? 0);
        if (! $roomId || ! $userId) return;

        $room = Room::find($roomId);
        if ($room?->host_user_id !== $conn['user_id']) return;

        Redis::hdel("call:{$roomId}:participants", $userId);

        $targetFd = static::getFdByUserId($userId, $server);
        if ($targetFd && $server->isEstablished($targetFd)) {
            $server->push($targetFd, json_encode(['type' => 'call.kicked', 'user_id' => $userId, 'room_id' => $roomId]));
        }

        static::broadcastToRoom($server, $roomId, ['type' => 'call.kicked', 'user_id' => $userId], exclude: $targetFd ?? -1);
    }

    // ── Background Change ─────────────────────────────────────────────────────

    private static function handleBgChange(Server $server, int $fd, array $conn, array $data): void
    {
        $roomId = $conn['room_id'];
        if (! $roomId) return;

        $room = \App\Models\Room::find($roomId);
        if ($room?->host_user_id !== $conn['user_id']) return;

        $bgUrl = $data['bg_url'] ?? null;
        if (! $bgUrl) return;

        $room->update(['current_bg_url' => $bgUrl]);

        static::broadcastToRoom($server, $roomId, ['type' => 'room.bg_change', 'bg_url' => $bgUrl]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private static function broadcastToRoom(Server $server, string $roomId, array $payload, int $exclude = -1): void
    {
        $fds  = Redis::smembers("room:{$roomId}:fds");
        $json = json_encode($payload);

        foreach ($fds as $fd) {
            $fd = (int) $fd;
            if ($fd === $exclude) continue;
            if ($server->isEstablished($fd)) {
                $server->push($fd, $json);
            } else {
                Redis::srem("room:{$roomId}:fds", $fd);
            }
        }
    }

    private static function getHostFd(string $roomId, ?Server $server = null): ?int
    {
        $hostUserId = Room::where('id', $roomId)->value('host_user_id');
        if (! $hostUserId) return null;
        return static::getFdByUserId($hostUserId, $server);
    }

    /**
     * FIX #2 / General: Always pass $server when available so the Redis fallback
     * validates FDs with isEstablished() and purges stale entries.
     */
    private static function getFdByUserId(int $userId, ?Server $server = null): ?int
    {
        // In-memory check first (primary truth with worker_num=1)
        foreach (static::$connections as $fd => $conn) {
            if ($conn['user_id'] === $userId) {
                // Even in-memory, verify with server if provided
                if ($server && ! $server->isEstablished((int) $fd)) {
                    continue;
                }
                return (int) $fd;
            }
        }

        // Redis fallback — iterate all cached FDs and validate each one
        $fds = Redis::smembers("ws:user:{$userId}:fds");
        foreach ($fds as $fd) {
            $fd = (int) $fd;
            if ($server && ! $server->isEstablished($fd)) {
                Redis::srem("ws:user:{$userId}:fds", $fd);
                Redis::del("ws:fd:{$fd}:user");
                continue;
            }
            return $fd;
        }
        return null;
    }
}
