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
        $rooms = Room::with('host:id,username,avatar_url,is_verified,level')
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
            'seat_count'       => $request->seat_count ?? 8,
            'agora_channel_id' => $channelId,
            'agora_token'      => $agoraToken,
            'status'           => 'live',
            'started_at'       => now(),
        ]);

        if ($request->type === 'audio_board') {
            $this->roomService->initSeats($room->id, $request->seat_count ?? 8);
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
            'host:id,username,avatar_url,is_verified,level',
            'seats.user:id,username,avatar_url',
        ])->findOrFail($roomId);

        $userId = auth()->id();

        if ($this->banService->isRoomBanned($userId, $roomId)) {
            return response()->json(['message' => 'You are banned from this room.'], 403);
        }

        $viewerToken = $this->agora->generateToken(
            $room->agora_channel_id,
            $userId,
            role: 'subscriber'
        );

        return response()->json([
            'room'            => $room,
            'agora_token'     => $viewerToken,
            'agora_app_id'    => $this->agora->getAppId(),
            'viewer_count'    => $this->roomService->getViewerCount($roomId),
            'is_following'    => auth()->user()->isFollowing($room->host_user_id),
            'current_user_id' => $userId,
            'user_coin_balance' => auth()->user()->coin_balance,
        ]);
    }

    public function end(string $roomId): JsonResponse
    {
        $room = Room::findOrFail($roomId);

        if ($room->host_user_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $room->update(['status' => 'ended', 'ended_at' => now()]);
        $this->roomService->cleanupRoom($roomId);

        return response()->json([
            'message' => 'Room ended.',
            'summary' => $this->roomService->getSummary($room),
        ]);
    }

    public function refreshToken(string $roomId): JsonResponse
    {
        $room   = Room::findOrFail($roomId);
        $userId = auth()->id();
        $role   = $room->host_user_id === $userId ? 'publisher' : 'subscriber';

        return response()->json([
            'agora_token' => $this->agora->generateToken($room->agora_channel_id, $userId, $role),
        ]);
    }

    public function heartbeat(string $roomId): JsonResponse
    {
        $this->roomService->recordHeartbeat($roomId, auth()->id());

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
