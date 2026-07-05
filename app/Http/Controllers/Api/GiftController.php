<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Gift;
use App\Models\GiftTransaction;
use App\Services\GiftService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GiftController extends Controller
{
    public function __construct(private readonly GiftService $giftService)
    {
    }

    public function index(): JsonResponse
    {
        $gifts = Gift::active()
            ->orderBy('sort_order')
            ->get()
            ->groupBy('category');

        return response()->json($gifts);
    }

    public function send(Request $request): JsonResponse
    {
        $data = $request->validate([
            'gift_id'        => ['required', 'exists:gifts,id'],
            'room_id'        => ['required', 'string'],
            'target_user_id' => ['required', 'exists:users,id'],
            'quantity'       => ['required', 'integer', 'min:1', 'max:99'],
        ]);

        $result = $this->giftService->sendGift(
            sender:       auth()->user(),
            giftId:       $data['gift_id'],
            roomId:       $data['room_id'],
            targetUserId: $data['target_user_id'],
            quantity:     $data['quantity'],
        );

        if (! $result['success']) {
            return response()->json(['message' => $result['message']], 422);
        }

        return response()->json([
            'message'        => 'Gift sent.',
            'coins_deducted' => $result['coins_deducted'],
            'new_balance'    => $result['new_balance'],
        ]);
    }

    // ── Monthly / weekly gifters to a specific host ──────────────────────────
    // GET /users/{hostId}/gifters?period=monthly|weekly&page=1
    public function hostGifters(string $hostId, Request $request): JsonResponse
    {
        $period = $request->query('period', 'monthly'); // monthly | weekly
        $perPage = 20;

        $from = match ($period) {
            'weekly'  => now()->startOfWeek(),
            default   => now()->startOfMonth(),
        };
        $to = now();

        $gifters = GiftTransaction::where('receiver_id', $hostId)
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('sender_id, SUM(diamond_total) as total_diamonds, COUNT(*) as gift_count')
            ->with('sender:id,username,display_name,avatar_url,frame_url,level')
            ->groupBy('sender_id')
            ->orderByDesc('total_diamonds')
            ->paginate($perPage);

        return response()->json([
            'data'          => $gifters->map(fn($g) => [
                'user_id'        => $g->sender?->id,
                'username'       => $g->sender?->display_name ?? $g->sender?->username ?? 'Unknown',
                'avatar_url'     => $g->sender?->avatar_url,
                'frame_url'      => $g->sender?->frame_url,
                'level'          => $g->sender?->level ?? 1,
                'total_diamonds' => (int) $g->total_diamonds,
                'gift_count'     => (int) $g->gift_count,
            ])->values(),
            'current_page'  => $gifters->currentPage(),
            'last_page'     => $gifters->lastPage(),
            'has_more'      => $gifters->hasMorePages(),
        ]);
    }

    public function topGifters(string $roomId): JsonResponse
    {
        // Returns top gifters for this room (all time in this session)
        // Each gifter includes frame_url and level for display
        $gifters = GiftTransaction::where('room_id', $roomId)
            ->selectRaw('sender_id, SUM(coin_total) as total_coins, COUNT(*) as gift_count')
            ->with('sender:id,username,display_name,avatar_url,frame_url,level')
            ->groupBy('sender_id')
            ->orderByDesc('total_coins')
            ->limit(50)
            ->get()
            ->map(fn($g) => [
                'user_id'      => $g->sender?->id,
                'username'     => $g->sender?->display_name ?? $g->sender?->username,
                'avatar_url'   => $g->sender?->avatar_url,
                'frame_url'    => $g->sender?->frame_url,
                'level'        => $g->sender?->level ?? 1,
                'total_coins'  => (int) $g->total_coins,
                'gift_count'   => (int) $g->gift_count,
            ]);

        return response()->json($gifters);
    }

    public function history(): JsonResponse
    {
        $sent = GiftTransaction::where('sender_id', auth()->id())
            ->with(['gift:id,name,thumbnail_url', 'receiver:id,username,avatar_url'])
            ->orderByDesc('created_at')
            ->paginate(30);

        return response()->json($sent);
    }
}