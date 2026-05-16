<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Room\CreateRoomRequest;
use App\Jobs\NotifyFollowersLiveJob;
use App\Models\Room;
use App\Services\AgoraService;
use App\Services\BanService;
use App\Services\LiveRoomService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Redis;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RoomController extends Controller
{
    public function __construct(
        private readonly AgoraService    $agora,
        private readonly LiveRoomService $roomService,
        private readonly BanService      $banService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $rooms = Room::with('host:id,username,display_name,avatar_url,is_verified,level')
            ->where('status', 'live')
            ->when($request->type, fn ($q, $t) => $q->where('type', $t))
            ->when($request->category, fn ($q, $c) => $q->where('category', $c))
            ->when($request->search, fn ($q, $s) => $q->where('title', 'like', "%{$s}%"))
            ->orderByDesc('viewer_count')
            ->paginate(20);

        return response()->json($rooms);
    }

    public function recommended(): JsonResponse
    {
        $followedIds = auth()->user()->following()->pluck('following_id');

        $following = Room::with('host:id,username,avatar_url,is_verified')
            ->where('status', 'live')
            ->whereIn('host_user_id', $followedIds)
            ->orderByDesc('viewer_count')
            ->limit(10)
            ->get();

        $trending = Room::with('host:id,username,avatar_url,is_verified')
            ->where('status', 'live')
            ->whereNotIn('host_user_id', $followedIds)
            ->orderByDesc('viewer_count')
            ->limit(20)
            ->get();

        return response()->json(compact('following', 'trending'));
    }

    public function store(CreateRoomRequest $request): JsonResponse
    {
        // Must be in an agency to go live
        if (! auth()->user()->agency_id) {
            return response()->json([
                'message' => 'You must join an agency before going live.',
                'code'    => 'no_agency',
            ], 403);
        }

        $user = auth()->user();

        // End any existing live room for this host
        Room::where('host_user_id', $user->id)
            ->where('status', 'live')
            ->update(['status' => 'ended', 'ended_at' => now()]);

        $channelId  = 'room_'.Str::uuid()->toString();
        $agoraToken = $this->agora->generateToken($channelId, $user->id);

        $room = Room::create([
            'id'               => Str::uuid(),
            'host_user_id'     => $user->id,
            'title'            => $request->title,
            'type'             => $request->type,
            'category'         => $request->category,
            'thumbnail_url'    => $request->thumbnail_url ?? $user->avatar_url,
            'seat_count'       => $request->seat_count ?? ($request->type === 'audio_board' ? 16 : 8),
            'agora_channel_id' => $channelId,
            'agora_token'      => $agoraToken,
            'status'           => 'live',
            'started_at'       => now(),
        ]);

        if ($request->type === 'audio_board') {
            $this->roomService->initSeats($room->id, $request->seat_count ?? 16);
        }

        NotifyFollowersLiveJob::dispatch($user->id, $room->id, $room->title);

        return response()->json([
            'room'        => $room->load('host'),
            'agora_token' => $agoraToken,
            'channel_id'  => $channelId,
        ], 201);
    }

    public function show(string $roomId): JsonResponse
    {
        $room = Room::with([
            'host:id,username,display_name,avatar_url,is_verified,level,diamond_balance',
            'seats.user:id,username,avatar_url',
        ])->findOrFail($roomId);

        $userId = auth()->id();

        if ($this->banService->isRoomBanned($userId, $roomId)) {
            return response()->json(['message' => 'You are banned from this room.'], 403);
        }

        $viewerToken = $this->agora->generateToken(
            $room->agora_channel_id,
            $userId,
            role: 'audience'
        );

        // Use live Redis viewer count, not stale DB count
        $liveViewerCount = $this->roomService->getViewerCount($roomId);

        return response()->json([
            'room'            => $room,
            'agora_token'     => $viewerToken,
            'agora_app_id'    => $this->agora->getAppId(),
            'viewer_count'    => $liveViewerCount,
            'is_following'    => auth()->user()->isFollowing($room->host_user_id),
            'current_bg_url'  => $room->current_bg_url,
            'current_user_id'     => $userId,
            'current_username'    => auth()->user()->username,
            'current_user_avatar' => auth()->user()->avatar_url,
            'current_user_level'  => auth()->user()->level ?? 1,
            'user_coin_balance'     => auth()->user()->coin_balance,
            'host_diamond_balance'  => $room->host->diamond_balance ?? 0,
        ]);
    }

    public function end(string $roomId): JsonResponse
    {
        $room = Room::findOrFail($roomId);

        if ($room->host_user_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $endedAt = now();
        $room->update(['status' => 'ended', 'ended_at' => $endedAt]);
        $this->roomService->cleanupRoom($roomId);

        // ── Live stats ────────────────────────────────────────────────────
        $host = auth()->user();

        // Calculate duration in minutes
        $startedAt      = $room->started_at ?? $room->created_at;
        $durationMins   = (int) $startedAt->diffInMinutes($endedAt);
        $durationHours  = (int) floor($durationMins / 60);
        $remainingMins  = $durationMins % 60;

        // Always add live time
        // Track today's live minutes in Redis for daily reward progress display
        $todayMinKey = "live_minutes_today:{$host->id}:" . now()->toDateString();
        \Illuminate\Support\Facades\Redis::incrby($todayMinKey, $durationMins);
        \Illuminate\Support\Facades\Redis::expire($todayMinKey, 86400 * 2);

        $updates = [
            'total_live_minutes' => \DB::raw("total_live_minutes + {$durationMins}"),
            'total_live_hours'   => \DB::raw("total_live_hours + {$durationHours}"),
            'total_streams'      => \DB::raw('total_streams + 1'),
        ];

        // Day count: only if session was >= 40 mins AND not already counted today
        if ($durationMins >= 40) {
            $type    = $room->type; // 'video' | 'audio' | 'audio_board'
            $dayKey  = "live_day_counted:{$host->id}:{$type}:" . now()->toDateString();
            $counted = \Illuminate\Support\Facades\Redis::get($dayKey);

            if (! $counted) {
                \Illuminate\Support\Facades\Redis::setex($dayKey, 86400, 1);
                if ($type === 'video') {
                    $updates['video_live_days'] = \DB::raw('video_live_days + 1');
                } else {
                    $updates['audio_live_days'] = \DB::raw('audio_live_days + 1');
                }
            }
        }

        $host->update($updates);

        // ── Diamond reward for 40+ minute video live ──────────────────────
        // Uses Redis SETNX (atomic set-if-not-exists) to prevent race conditions.
        // If multiple requests race, only ONE will get SETNX = 1 (success).
        // The others get 0 and are skipped — no double crediting.
        $diamondReward = 0;
        if ($durationMins >= 40 && $room->type === 'video') {
            $today     = now()->toDateString();
            $rewardKey = "diamond_reward_given:{$host->id}:{$today}";

            // SETNX is atomic — only succeeds for the FIRST caller
            $claimed = \Illuminate\Support\Facades\Redis::command('SETNX', [$rewardKey, 1]);

            if ($claimed) {
                // Set expiry separately (SETNX doesn't support TTL)
                \Illuminate\Support\Facades\Redis::expire($rewardKey, 86400 * 2);

                // Credit diamonds inside a DB transaction for safety
                \Illuminate\Support\Facades\DB::transaction(function () use ($host, $roomId, &$diamondReward, $today) {
                    $host->increment('diamond_balance', 5000);
                    $diamondReward = 5000;

                    \App\Models\CoinTransaction::create([
                        'user_id'      => $host->id,
                        'type'         => 'live_reward',
                        'amount'       => 5000,
                        'balance_after'=> $host->fresh()->diamond_balance,
                        'reference'    => "live_reward:room:{$roomId}:{$today}",
                    ]);
                });
            }
        }
        // ─────────────────────────────────────────────────────────────────

        return response()->json([
            'message'        => 'Room ended.',
            'duration_mins'  => $durationMins,
            'diamond_reward' => $diamondReward,
            'summary'        => $this->roomService->getSummary($room),
        ]);
    }

    public function refreshToken(string $roomId): JsonResponse
    {
        $room   = Room::findOrFail($roomId);
        $userId = auth()->id();
        $role   = $room->host_user_id === $userId ? 'broadcaster' : 'audience';

        return response()->json([
            'agora_token' => $this->agora->generateToken($room->agora_channel_id, $userId, $role),
        ]);
    }

    public function viewers(string $roomId): JsonResponse
    {
        // Get active viewer fds from Redis, map to user info
        $fds = Redis::smembers("room:{$roomId}:fds");

        // Collect user IDs from all fds first
        $userIds = [];
        foreach ($fds as $fd) {
            $userId = Redis::get("ws:fd:{$fd}:user");
            if ($userId) $userIds[] = (int) $userId;
        }

        if (empty($userIds)) {
            return response()->json(['viewers' => []]);
        }

        // Single query for all users
        $users = User::select('id', 'username', 'display_name', 'avatar_url', 'level')
            ->whereIn('id', array_unique($userIds))
            ->get()
            ->keyBy('id');

        // Single query for following status
        $myFollowing = auth()->user()
            ->following()->pluck('following_id')->toArray();

        $viewers = $users->map(fn ($user) => [
            'user_id'      => $user->id,
            'username'     => $user->username,
            'display_name' => $user->display_name,
            'avatar_url'   => $user->avatar_url,
            'level'        => $user->level,
            'is_following' => in_array($user->id, $myFollowing),
        ])->values()->toArray();

        return response()->json(['viewers' => $viewers]);
    }

    public function viewerCount(string $roomId): JsonResponse
    {
        // Count actual WS connections in the room (most reliable)
        $fdsCount    = (int) Redis::scard("room:{$roomId}:fds");
        $redisCount  = (int) (Redis::get("room:{$roomId}:viewers") ?? 0);

        // Use the larger of the two to avoid showing 0 when Redis is slightly stale
        $count = max($fdsCount > 0 ? $fdsCount - 1 : 0, $redisCount);

        return response()->json(['viewer_count' => $count]);
    }

    public function heartbeat(string $roomId): JsonResponse
    {
        $userId = auth()->id();
        $room   = Room::findOrFail($roomId);

        $this->roomService->recordHeartbeat($roomId, $userId);

        // If this is the host, update their specific heartbeat timestamp
        // Used by CleanupStaleRoomsJob to detect disconnected hosts
        if ($room->host_user_id === $userId) {
            \Illuminate\Support\Facades\Redis::setex(
                "room:{$roomId}:host_heartbeat",
                300,          // auto-expire after 5 min (safety net)
                time()
            );
        }

        return response()->json(['ok' => true]);
    }

    public function history(): JsonResponse
    {
        $rooms = Room::where('host_user_id', auth()->id())
            ->where('status', 'ended')
            ->orderByDesc('started_at')
            ->paginate(20);

        return response()->json($rooms);
    }
}