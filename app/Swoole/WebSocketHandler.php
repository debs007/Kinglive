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
            'level'    => (int) ($user->level ?? 1),
        ];

        // Clean stale fds for this user before adding new one
        $oldFds = Redis::smembers("ws:user:{$user->id}:fds");
        foreach ($oldFds as $oldFd) {
            $oldFd = (int) $oldFd;
            if (!$server->isEstablished($oldFd)) {
                Redis::srem("ws:user:{$user->id}:fds", $oldFd);
                Redis::del("ws:fd:{$oldFd}:user");
                // Do NOT remove from room:{roomId}:fds here —
                // handleRoomJoin manages room membership correctly.
                // Removing here would kick the host out of their own room set.
                unset(static::$connections[$oldFd]);
            }
        }
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
                'stream.end'          => static::handleStreamEnd($server, $fd, $conn, $data),
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
                'seat.emoji'          => static::handleSeatEmoji($server, $fd, $conn, $data),
                'screen.share'        => static::handleScreenShare($server, $fd, $conn, $data),
                'pk.random_toggle'    => static::handleRandomPkToggle($server, $fd, $conn, $data),
                'video.play'          => static::handleVideoEvent($server, $fd, $conn, $data),
                'video.pause'         => static::handleVideoEvent($server, $fd, $conn, $data),
                'video.seek'          => static::handleVideoEvent($server, $fd, $conn, $data),
                'video.stop'          => static::handleVideoEvent($server, $fd, $conn, $data),
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

            if ($isHost) {
                $room?->update(['status' => 'ended', 'ended_at' => now()]);
                static::broadcastToRoom($server, $roomId, [
                    'type'    => 'room.ended',
                    'room_id' => $roomId,
                ], crossBroadcast: false);  // never cross-broadcast room.ended
                Redis::del(
                    "room:{$roomId}:fds",
                    "room:{$roomId}:viewers",
                    "room:{$roomId}:seats",
                    "room:{$roomId}:host_heartbeat",
                    "room:{$roomId}:heartbeats",
                );
                Log::info("WS: host ended room {$roomId}");
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

        if (! in_array($room->status, ['live', 'waiting'])) {
            $server->push($fd, json_encode(['type' => 'error', 'message' => 'Room has ended.']));
            return;
        }

        $banService = app(BanService::class);
        if ($banService->isRoomBanned($conn['user_id'], $roomId)) {
            $server->push($fd, json_encode(['type' => 'room.banned', 'room_id' => $roomId, 'message' => 'You are banned from this room.']));
            return;
        }

        $conn['room_id'] = $roomId;

        $existingFds = Redis::smembers("room:{$roomId}:fds");
        foreach ($existingFds as $existingFd) {
            $existingUser = Redis::get("ws:fd:{$existingFd}:user");
            if ($existingUser == $conn['user_id'] && $existingFd != $fd) {
                Redis::srem("room:{$roomId}:fds", $existingFd);
            }
        }
        Redis::srem("room:{$roomId}:fds", $fd);
        Redis::sadd("room:{$roomId}:fds", $fd);
        Redis::expire("room:{$roomId}:fds", 86400);

        $room = Room::find($roomId);
        $isHost = $room?->host_user_id === $conn['user_id'];

        if (! $isHost) {
            $newCount = Redis::incr("room:{$roomId}:viewers");
            Room::where('id', $roomId)->update(['viewer_count' => (int) $newCount]);
        }

        $currentViewerCount = (int) Redis::get("room:{$roomId}:viewers");

        // Only broadcast user.joined for non-host joins
        // Host joining their own room should not show as a viewer join
        if (! $isHost) {
            static::broadcastToRoom($server, $roomId, [
                'type'         => 'user.joined',
                'room_id'      => $roomId,
                'user_id'      => $conn['user_id'],
                'username'     => $conn['username'],
                'avatar'       => $conn['avatar'],
                'level'        => (int) ($conn['level'] ?? 1),
                'viewer_count' => $currentViewerCount,
            ], exclude: $fd, crossBroadcast: false);
        }

        // Also send updated count to the joiner themselves
        $server->push($fd, json_encode([
            'type'         => 'viewer.count',
            'viewer_count' => $currentViewerCount,
        ]));

        $allSeats = Redis::hgetall("room:{$roomId}:seats") ?: [];
        foreach ($allSeats as $seatIdx => $seatJson) {
            $seatData = json_decode($seatJson, true);
            if (! isset($seatData['user_id'])) continue;
            $seatUserId = (int) $seatData['user_id'];
            $seatFd     = static::getFdByUserId($seatUserId);
            if (! $seatFd || ! $server->isEstablished($seatFd)) {
                Redis::hdel("room:{$roomId}:seats", $seatIdx);
                static::broadcastToRoom($server, $roomId, ['type' => 'seat.vacated', 'seat_index' => (int) $seatIdx]);
            }
        }

        $chatRaw             = Redis::zrange("room:{$roomId}:chat", -50, -1);
        $chat                = array_map(fn ($c) => json_decode($c, true), $chatRaw);
        $callParticipantsRaw = Redis::hgetall("call:{$roomId}:participants") ?: [];
        $callParticipants    = array_values(array_map(fn ($p) => json_decode($p, true), $callParticipantsRaw));

        // Include current background so new joiners see it immediately
        $room          = \App\Models\Room::find($roomId);
        $currentBgUrl  = $room?->current_bg_url;

        // Build seats as stdClass so json_encode always produces a JSON object
        // {"0":{...}, "2":{...}} regardless of key sequence.
        // PHP encodes sequential integer-keyed arrays as JSON arrays which
        // breaks Flutter's Map parsing and causes seats to not show.
        $rawSeatHash = Redis::hgetall("room:{$roomId}:seats") ?: [];
        $seatsObj    = new \stdClass();
        foreach ($rawSeatHash as $seatIdx => $seatJson) {
            $decoded = json_decode($seatJson, true);
            if (is_array($decoded)) {
                $seatsObj->$seatIdx = $decoded;
            }
        }

        // Include screen share + video state for late joiners
        $screenShareRaw = Redis::get("room:{$roomId}:screen_share");
        $screenShare    = $screenShareRaw ? json_decode($screenShareRaw, true) : null;

        $videoStateRaw  = Redis::get("room:{$roomId}:video_state");
        $videoState     = $videoStateRaw  ? json_decode($videoStateRaw,  true) : null;

        // If video is playing, update the position with time elapsed since last sync
        if ($videoState && ($videoState['is_playing'] ?? false)) {
            $elapsed             = time() - ($videoState['timestamp'] ?? time());
            $videoState['position'] = ($videoState['position'] ?? 0) + $elapsed;
            $videoState['timestamp'] = time(); // reset timestamp to now
        }

        $server->push($fd, json_encode([
            'type'              => 'room.state',
            'room_id'           => $roomId,
            'viewer_count'      => (int) Redis::get("room:{$roomId}:viewers"),
            'recent_chat'       => array_values($chat),
            'seats'             => $seatsObj,
            'call_participants' => $callParticipants,
            'current_bg_url'    => $currentBgUrl,
            'screen_share'      => $screenShare,
            'video_state'       => $videoState,
        ]));

        $pkSessionId = Redis::get("pk:room:{$roomId}");
        if ($pkSessionId) {
            $pkRaw = Redis::get("pk:session:{$pkSessionId}");
            if ($pkRaw) {
                $pkSession = json_decode($pkRaw, true);
                $agoraService   = app(\App\Services\AgoraService::class);
                $challengerRoom = $pkSession['challenger_room'] ?? null;
                $targetRoom     = $pkSession['target_room']     ?? null;
                $isInChallengerRoom = $roomId === $challengerRoom;
                $opponentRoomId = $isInChallengerRoom ? $targetRoom : $challengerRoom;
                $opponentUid    = $isInChallengerRoom
                    ? ($pkSession['target_uid']     ?? 0)
                    : ($pkSession['challenger_uid'] ?? 0);
                $opponentToken  = $opponentRoomId
                    ? $agoraService->generateToken($opponentRoomId, $conn['user_id'], 'audience')
                    : '';

                $server->push($fd, json_encode([
                    'type'                 => 'pk.running',
                    'pk_session_id'        => $pkSessionId,
                    'challenger_room'      => $challengerRoom,
                    'target_room'          => $targetRoom,
                    'scores'               => $pkSession['scores'],
                    'started_at'           => $pkSession['started_at'],
                    'duration'             => $pkSession['duration'],
                    'pk_channel_id'        => null,
                    'challenger_uid'       => $pkSession['challenger_uid'] ?? null,
                    'target_uid'           => $pkSession['target_uid'] ?? null,
                    'challenger_name'      => $pkSession['challenger_name'] ?? '',
                    'target_name'          => $pkSession['target_name'] ?? '',
                    'challenger_avatar'    => $pkSession['challenger_avatar'] ?? '',
                    'target_avatar'        => $pkSession['target_avatar'] ?? '',
                    'challenger_agora_uid' => $pkSession['challenger_uid'] ?? null,
                    'target_agora_uid'     => $pkSession['target_uid'] ?? null,
                    'opponent_channel_id'  => $opponentRoomId,
                    'opponent_token'       => $opponentToken,
                    'opponent_uid'         => $opponentUid,
                ]));
            }
        }
    }

    private static function handleStreamEnd(Server $server, int $fd, array $conn, array $data): void
    {
        $roomId = $conn['room_id'] ?? ($data['room_id'] ?? null);
        if (! $roomId) return;

        $room = \App\Models\Room::find($roomId);
        if ($room?->host_user_id !== $conn['user_id']) return;

        // Broadcast room.ended to audience — exclude host so they don't get the dialog
        static::broadcastToRoom($server, $roomId, [
            'type'    => 'room.ended',
            'room_id' => $roomId,
        ], exclude: $fd, crossBroadcast: false);
    }

    private static function handleRoomLeave(Server $server, int $fd, array &$conn): void
    {
        if ($conn['room_id']) static::removeFromRoom($server, $fd, $conn);
    }

    private static function removeFromRoom(Server $server, int $fd, array &$conn): void
    {
        $roomId = $conn['room_id'];
        Redis::srem("room:{$roomId}:fds", $fd);

        $room = Room::find($roomId);
        if ($room?->host_user_id !== $conn['user_id']) {
            $remaining = (int) Redis::decr("room:{$roomId}:viewers");
            if ($remaining < 0) {
                $remaining = 0;
                Redis::set("room:{$roomId}:viewers", 0);
            }
            Room::where('id', $roomId)->update(['viewer_count' => $remaining]);
        }

        Redis::del("room:{$roomId}:user_pending:{$conn['user_id']}");

        // FIX: Clean up call participants on abrupt disconnect
        // Handles case where call.leave was never sent (app crash, network drop)
        // Without this, new joiners see ghost participants in room.state
        if (Redis::hexists("call:{$roomId}:participants", $conn['user_id'])) {
            Redis::hdel("call:{$roomId}:participants", $conn['user_id']);
        // If no participants left, remove host too
        if (Redis::hlen("call:{$roomId}:participants") <= 1) {
            Redis::del("call:{$roomId}:participants");
        }
            static::broadcastToRoom($server, $roomId, [
                'type'    => 'call.left',
                'user_id' => $conn['user_id'],
            ]);
        }

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
        ], crossBroadcast: false);

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
            'level'    => (int) ($conn['level'] ?? 1),
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
            'level'    => (int) ($conn['level'] ?? 1),
            'message'  => $message,
        ], exclude: $fd);
    }

    // ── DM (Direct Message) ───────────────────────────────────────────────────

    /**
     * Push a DM message WS event to the receiver.
     * Called from DirectMessageController after saving the message.
     * Uses a static method so it can be called from outside (via app()->make).
     */
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
        if ($prevUserId && $prevUserId != $conn['user_id']) {
            $prevFd = static::getFdByUserId((int) $prevUserId);
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

        $targetFd = static::getFdByUserId($userId);

        Redis::del("room:{$roomId}:seat_request:{$seatIndex}");
        Redis::del("room:{$roomId}:user_pending:{$userId}");

        if ($accepted) {
            // Check seat not already taken (race condition guard)
            $existing = Redis::hget("room:{$roomId}:seats", $seatIndex);
            if ($existing) {
                $existingSeat = json_decode($existing, true);
                if (isset($existingSeat['user_id'])) {
                    // Seat already taken — deny this request
                    if ($targetFd && $server->isEstablished($targetFd)) {
                        $server->push($targetFd, json_encode([
                            'type'       => 'seat.response',
                            'accepted'   => false,
                            'seat_index' => -1,
                            'reason'     => 'Seat already taken',
                        ]));
                    }
                    return;
                }
            }

            $agoraUid = (int) $userId;

            Redis::hset("room:{$roomId}:seats", $seatIndex, json_encode([
                'user_id'   => $userId,
                'username'  => static::$connections[$targetFd]['username'] ?? '',
                'avatar'    => static::$connections[$targetFd]['avatar'] ?? '',
                'agora_uid' => $agoraUid,
            ]));

            // Auto-deny any OTHER pending requests for this same seat
            $allPendingKeys = Redis::keys("room:{$roomId}:user_pending:*");
            foreach ($allPendingKeys as $key) {
                $pendingSeatIdx = (int) Redis::get($key);
                if ($pendingSeatIdx === $seatIndex) {
                    // Extract user_id from key: room:{roomId}:user_pending:{userId}
                    $parts         = explode(':', $key);
                    $pendingUserId = (int) end($parts);
                    if ($pendingUserId !== $userId) {
                        Redis::del($key);
                        Redis::del("room:{$roomId}:seat_request:{$seatIndex}");
                        $pendingFd = static::getFdByUserId($pendingUserId);
                        if ($pendingFd && $server->isEstablished($pendingFd)) {
                            $server->push($pendingFd, json_encode([
                                'type'       => 'seat.response',
                                'accepted'   => false,
                                'seat_index' => -1,
                                'reason'     => 'Seat was taken by another user',
                            ]));
                        }
                    }
                }
            }

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

        $targetFd = static::getFdByUserId((int) $userId);
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

        $targetFd = static::getFdByUserId($targetUserId);
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

        // Process gift synchronously to get level update immediately
        $giftService = app(\App\Services\GiftService::class);
        $sender      = \App\Models\User::find($conn['user_id']);
        $result      = $giftService->sendGift(
            sender:       $sender,
            giftId:       $giftId,
            roomId:       $roomId,
            targetUserId: $targetUserId,
            quantity:     $quantity,
        );

        if (! ($result['success'] ?? false)) {
            $server->push($fd, json_encode([
                'type'    => 'gift.error',
                'message' => $result['message'] ?? 'Gift failed.',
            ]));
            return;
        }

        // Send updated balance to sender
        $server->push($fd, json_encode([
            'type'         => 'balance.update',
            'coin_balance' => $result['new_balance'],
            'level'        => $result['current_level'],
        ]));

        // Notify sender of level up
        if (! empty($result['new_level'])) {
            $server->push($fd, json_encode([
                'type'      => 'level.up',
                'new_level' => $result['new_level'],
                'user_id'   => $conn['user_id'],
            ]));
            // Broadcast to room so others see the level badge update
            static::broadcastToRoom($server, $roomId, [
                'type'      => 'level.up',
                'new_level' => $result['new_level'],
                'user_id'   => $conn['user_id'],
            ], exclude: $fd);
        }

        // Balance update sent after gift processing below

        $totalDiamonds      = ($gift->diamond_value ?? 0) * $quantity;
        $room               = \App\Models\Room::find($roomId);
        // GiftService already credited diamonds to host — read actual DB balance
        $hostDiamondBalance = $room
            ? (\App\Models\User::find($room->host_user_id)?->diamond_balance ?? 0)
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
            'sender_level'   => (int) ($conn['level'] ?? 1),
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
            $challengerFd = static::getFdByUserId((int) $invite['challenger_uid']);
            if ($challengerFd && $server->isEstablished($challengerFd)) {
                $server->push($challengerFd, json_encode(['type' => 'pk.declined', 'pk_session_id' => $pkSessionId]));
            }

            // If this was a random PK invite, move to next candidate
            if (! empty($invite['random'])) {
                $roomType   = $invite['room_type']     ?? 'video';
                $challengerRoomId = $invite['challenger_room'] ?? null;
                if ($challengerRoomId) {
                    $myPoolKey = "pk:random:{$roomType}:{$challengerRoomId}";
                    $myData    = Redis::get($myPoolKey);
                    if ($myData) {
                        $myConn = json_decode($myData, true);
                        static::sendNextRandomPkInvite(
                            $server, $challengerFd ?? -1,
                            [
                                'room_id'  => $challengerRoomId,
                                'user_id'  => $myConn['user_id'],
                                'username' => $myConn['username'],
                                'avatar'   => $myConn['avatar'],
                            ],
                            $challengerRoomId,
                            $roomType
                        );
                    }
                }
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

        // Each host gets a token to subscribe to the OPPONENT's channel
        // Host stays on their own channel — no channel switching needed
        $challengerRoom   = $invite['challenger_room'];
        $targetRoom       = $conn['room_id'];

        // Challenger subscribes to target's room channel
        $opponentTokenForChallenger = $agoraService->generateToken(
            $targetRoom, $challengerAgoraUid, 'audience'
        );

        // Target subscribes to challenger's room channel
        $opponentTokenForTarget = $agoraService->generateToken(
            $challengerRoom, $targetAgoraUid, 'audience'
        );

        $challengerHostFd = static::getFdByUserId($challengerAgoraUid);
        if ($challengerHostFd && $server->isEstablished($challengerHostFd)) {
            $server->push($challengerHostFd, json_encode([
                'type'                => 'pk.join_channel',
                'opponent_channel_id' => $targetRoom,   // subscribe to opponent's room
                'opponent_token'      => $opponentTokenForChallenger,
                'opponent_uid'        => $targetAgoraUid,
                'opponent_name'       => $conn['username'],
                'opponent_avatar'     => $conn['avatar'] ?? '',
            ]));
        }

        $server->push($fd, json_encode([
            'type'                => 'pk.join_channel',
            'opponent_channel_id' => $challengerRoom,   // subscribe to opponent's room
            'opponent_token'      => $opponentTokenForTarget,
            'opponent_uid'        => $challengerAgoraUid,
            'opponent_name'       => $invite['challenger_name'],
            'opponent_avatar'     => $invite['challenger_avatar'] ?? '',
        ]));

        // For challenger room audience: opponent is target room
        $broadcastToChallenger = array_merge($broadcast, [
            'opponent_channel_id' => $conn['room_id'],
            'opponent_token'      => $agoraService->generateToken($conn['room_id'], 0, 'audience'),
            'opponent_uid'        => $targetAgoraUid,
        ]);

        // For target room audience: opponent is challenger room
        $broadcastToTarget = array_merge($broadcast, [
            'opponent_channel_id' => $invite['challenger_room'],
            'opponent_token'      => $agoraService->generateToken($invite['challenger_room'], 0, 'audience'),
            'opponent_uid'        => $challengerAgoraUid,
        ]);

        static::broadcastToRoom($server, $invite['challenger_room'], $broadcastToChallenger);
        static::broadcastToRoom($server, $conn['room_id'], $broadcastToTarget);

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
        static::broadcastToRoom($server, $session['challenger_room'], $scoreBroadcast, crossBroadcast: false);
        static::broadcastToRoom($server, $session['target_room'], $scoreBroadcast, crossBroadcast: false);
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

        $targetFd = static::getFdByUserId($targetUserId);
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

        $targetFd = static::getFdByUserId($targetUserId);
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
        // Process up to 10 pending broadcasts per tick
        for ($i = 0; $i < 10; $i++) {
            $raw = Redis::rpop("ws:pending_broadcasts");
            if (! $raw) break;

            $item = json_decode($raw, true);
            if (! $item || time() > ($item['expires'] ?? 0)) continue;

            $payload = json_decode($item['payload'], true);

            foreach ($item['rooms'] as $roomId) {
                static::broadcastToRoom($server, $roomId, $payload, crossBroadcast: false);
            }

            // If this is a room shutdown, also end the room in DB
            if (($payload['type'] ?? '') === 'room.admin_off') {
                $roomId = $item['rooms'][0] ?? null;
                if ($roomId) {
                    \App\Models\Room::where('id', $roomId)
                        ->where('status', 'live')
                        ->update(['status' => 'ended', 'ended_at' => now()]);
                    app(\App\Services\LiveRoomService::class)->cleanupRoom($roomId);
                    Log::info("Admin forced room {$roomId} off via broadcast queue");
                }
            }
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

        $targetFd = static::getFdByUserId($targetUserId);
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
            $targetFd = static::getFdByUserId($followerId);
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

        $targetFd = static::getFdByUserId($userId);
        if (! $targetFd || ! $server->isEstablished($targetFd)) return;

        if ($accepted) {
            $callKey    = "call:{$roomId}:participants";
            $agoraUid   = $userId;
            $targetConn = static::$connections[$targetFd] ?? [];

            // Add the accepted participant
            Redis::hset($callKey, $userId, json_encode([
                'user_id'    => $userId,
                'username'   => $targetConn['username'] ?? '',
                'avatar'     => $targetConn['avatar']   ?? '',
                'agora_uid'  => $agoraUid,
                'camera_off' => true,
            ]));

            // NOTE: host is NOT stored in call participants hash —
            // host is shown as main video, not as a participant tile
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
        // If no participants left, remove host too
        if (Redis::hlen("call:{$roomId}:participants") <= 1) {
            Redis::del("call:{$roomId}:participants");
        }
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

        $targetFd = static::getFdByUserId($userId);
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

    // ── Seat Emoji ───────────────────────────────────────────────────────────────

    private static function handleSeatEmoji(Server $server, int $fd, array $conn, array $data): void
    {
        $roomId     = $conn['room_id'];
        $emojiIndex = isset($data['emoji_index']) ? (int) $data['emoji_index'] : -1;
        if (! $roomId || $emojiIndex < 0) return;

        $seatIndex = -1;
        $room      = \App\Models\Room::find($roomId);
        if ($room?->host_user_id !== $conn['user_id']) {
            $seats = Redis::hgetall("room:{$roomId}:seats") ?: [];
            foreach ($seats as $idx => $seatJson) {
                $seat = json_decode($seatJson, true);
                if (($seat['user_id'] ?? null) == $conn['user_id']) {
                    $seatIndex = (int) $idx;
                    break;
                }
            }
            if ($seatIndex === -1) return;
        }

        static::broadcastToRoom($server, $roomId, [
            'type'        => 'seat.emoji',
            'seat_index'  => $seatIndex,
            'emoji_index' => $emojiIndex,
            'user_id'     => $conn['user_id'],
        ], exclude: $fd);
    }

    // ── Random PK Matchmaking ────────────────────────────────────────────────────

    private static function handleRandomPkToggle(Server $server, int $fd, array $conn, array $data): void
    {
        $roomId   = $conn['room_id'];
        $enabled  = (bool) ($data['enabled']   ?? false);
        $roomType = $data['room_type'] ?? 'video';
        if (! $roomId) return;

        $room = \App\Models\Room::find($roomId);
        if ($room?->host_user_id !== $conn['user_id']) return;

        if (! $enabled) {
            // Host disabled — remove from pool and clear any pending state
            Redis::del("pk:random:{$roomType}:{$roomId}");
            Redis::del("pk:random:pending:{$roomId}");
            return;
        }

        // Register in pool
        Redis::setex("pk:random:{$roomType}:{$roomId}", 300, json_encode([
            'room_id'   => $roomId,
            'user_id'   => $conn['user_id'],
            'username'  => $conn['username'],
            'avatar'    => $conn['avatar'],
        ]));

        // Try to send invite to next available host
        static::sendNextRandomPkInvite($server, $fd, $conn, $roomId, $roomType);
    }

    /**
     * Send a random PK invite to the next available host in the pool.
     * Called on toggle-on AND after a rejection to try the next candidate.
     */
    private static function sendNextRandomPkInvite(
        Server $server,
        int    $fd,
        array  $conn,
        string $roomId,
        string $roomType
    ): void {
        // Track already-tried rooms to skip them
        $triedKey = "pk:random:tried:{$roomId}";
        $tried    = json_decode(Redis::get($triedKey) ?? '[]', true) ?: [];

        // Map room type to DB column value
        // Flutter sends 'video', 'audio', 'audioBoard' — DB stores 'video','audio','audio_board'
        $dbType = match($roomType) {
            'audioBoard' => 'audio_board',
            default      => $roomType,
        };

        // Scan ALL live rooms of the same type — not just those with random PK on
        $liveRooms = \App\Models\Room::where('status', 'live')
            ->where('type', $dbType)
            ->where('id', '!=', $roomId)
            ->whereNotIn('id', $tried)
            ->with('host:id,username,avatar_url')
            ->get();

        if ($liveRooms->isEmpty()) {
            Redis::del($triedKey);
            return;
        }

        foreach ($liveRooms as $room) {
            $hostId = $room->host_user_id;

            // Skip own rooms / self
            if ($hostId == $conn['user_id']) continue;

            // Skip rooms already in a PK
            if (Redis::get("pk:room:{$room->id}")) continue;

            // Verify host is connected via WebSocket
            $candidateFd = static::getFdByUserId((int) $hostId, $server);
            if (! $candidateFd || ! $server->isEstablished($candidateFd)) continue;

            $pkSessionId = (string) \Illuminate\Support\Str::uuid();

            // Mark as tried so we don't re-invite on next cycle
            $tried[] = $room->id;
            Redis::setex($triedKey, 300, json_encode($tried));

            $hostName   = $room->host?->username   ?? 'Host';
            $hostAvatar = $room->host?->avatar_url  ?? '';

            // Store invite so pk.response can look it up
            Redis::setex("pk:invite:{$pkSessionId}", 30, json_encode([
                'challenger_room'   => $roomId,
                'challenger_uid'    => $conn['user_id'],
                'challenger_name'   => $conn['username'],
                'challenger_avatar' => $conn['avatar'],
                'target_room'       => $room->id,
                'target_uid'        => $hostId,
                'random'            => true,
                'room_type'         => $roomType,
            ]));

            // Only TARGET host gets the invite popup — challenger just waits
            $server->push($candidateFd, json_encode([
                'type'               => 'pk.random_invite',
                'pk_session_id'      => $pkSessionId,
                'challenger_room_id' => $roomId,
                'challenger_name'    => $conn['username'],
                'challenger_avatar'  => $conn['avatar'],
                'auto_reject_secs'   => 5,
            ]));

            // Tell challenger we found someone and are waiting
            $server->push($fd, json_encode([
                'type'           => 'pk.random_searching',
                'target_name'    => $hostName,
                'target_avatar'  => $hostAvatar,
                'pk_session_id'  => $pkSessionId,
            ]));

            return; // Wait for response, then try next if declined
        }

        // Exhausted all live rooms — clear tried so next toggle starts fresh
        Redis::del($triedKey);
    }

    // ── Screen Share ─────────────────────────────────────────────────────────────

    private static function handleScreenShare(Server $server, int $fd, array $conn, array $data): void
    {
        $roomId = $conn['room_id'];
        if (! $roomId) return;

        $room = \App\Models\Room::find($roomId);
        if ($room?->host_user_id !== $conn['user_id']) return;

        $sharing    = (bool) ($data['sharing']     ?? false);
        $youtubeUrl = $data['youtube_url'] ?? null;

        if ($sharing) {
            Redis::setex("room:{$roomId}:screen_share", 86400, json_encode([
                'sharing'     => true,
                'youtube_url' => $youtubeUrl,
            ]));
        } else {
            Redis::del("room:{$roomId}:screen_share");
        }

        static::broadcastToRoom($server, $roomId, [
            'type'        => 'screen.share',
            'sharing'     => $sharing,
            'youtube_url' => $youtubeUrl,
        ], exclude: $fd);
    }

    // ── Party Video Events ───────────────────────────────────────────────────────

    private static function handleVideoEvent(Server $server, int $fd, array $conn, array $data): void
    {
        $roomId = $conn['room_id'];
        if (! $roomId) return;

        // Only host can control video
        $room = \App\Models\Room::find($roomId);
        if ($room?->host_user_id !== $conn['user_id']) return;

        $type      = $data['type'];      // video.play / video.pause / video.seek / video.stop
        $videoUrl  = $data['video_url']  ?? null;
        $position  = (float) ($data['position']  ?? 0);  // seconds
        $timestamp = time(); // server timestamp for sync

        // Store state in Redis for late joiners
        if ($type === 'video.stop') {
            Redis::del("room:{$roomId}:video_state");
        } else {
            Redis::setex("room:{$roomId}:video_state", 86400, json_encode([
                'type'       => $type,
                'video_url'  => $videoUrl,
                'position'   => $position,
                'timestamp'  => $timestamp, // when this position was recorded
                'is_playing' => $type === 'video.play',
            ]));
        }

        // Broadcast to ALL including host (host needs to update state too)
        static::broadcastToRoom($server, $roomId, [
            'type'      => $type,
            'video_url' => $videoUrl,
            'position'  => $position,
            'timestamp' => $timestamp,
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private static function broadcastToRoom(
        Server $server,
        string $roomId,
        array  $payload,
        int    $exclude         = -1,
        bool   $crossBroadcast  = true  // cross-broadcast to PK opponent room
    ): void {
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

        // During PK — also broadcast to the opponent room (opt-out for PK system events)
        // so audiences of both rooms share unified chat, gifts, animations
        if ($crossBroadcast) { $pkSessionId = Redis::get("pk:room:{$roomId}");
        if ($pkSessionId) {
            $pkRaw = Redis::get("pk:session:{$pkSessionId}");
            if ($pkRaw) {
                $pkSession    = json_decode($pkRaw, true);
                $challengerRoom = $pkSession['challenger_room'] ?? null;
                $targetRoom     = $pkSession['target_room']     ?? null;

                // Find the opponent room
                $opponentRoomId = null;
                if ($challengerRoom && $challengerRoom !== $roomId) {
                    $opponentRoomId = $challengerRoom;
                } elseif ($targetRoom && $targetRoom !== $roomId) {
                    $opponentRoomId = $targetRoom;
                }

                if ($opponentRoomId) {
                    $opponentFds = Redis::smembers("room:{$opponentRoomId}:fds");
                    foreach ($opponentFds as $ofd) {
                        $ofd = (int) $ofd;
                        if ($ofd === $exclude) continue;
                        if ($server->isEstablished($ofd)) {
                            $server->push($ofd, $json);
                        } else {
                            Redis::srem("room:{$opponentRoomId}:fds", $ofd);
                        }
                    }
                }
            }
        }
        } // end crossBroadcast
    }

    private static function getHostFd(string $roomId, ?Server $server = null): ?int
    {
        $hostUserId = Room::where('id', $roomId)->value('host_user_id');
        if (! $hostUserId) return null;
        return static::getFdByUserId($hostUserId, $server);
    }

    private static function getFdByUserId(int $userId, ?Server $server = null): ?int
    {
        // First check in-memory connections (most reliable)
        foreach (static::$connections as $fd => $conn) {
            if ($conn['user_id'] === $userId) return (int) $fd;
        }
        // Fallback: check Redis, but clean stale fds
        $fds = Redis::smembers("ws:user:{$userId}:fds");
        foreach ($fds as $fd) {
            $fd = (int) $fd;
            if ($server && !$server->isEstablished($fd)) {
                // Stale fd — clean up
                Redis::srem("ws:user:{$userId}:fds", $fd);
                Redis::del("ws:fd:{$fd}:user");
                continue;
            }
            return $fd;
        }
        return null;
    }
}