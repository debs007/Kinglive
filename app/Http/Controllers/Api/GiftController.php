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

    public function topGifters(string $roomId): JsonResponse
    {
        $gifters = GiftTransaction::where('room_id', $roomId)
            ->selectRaw('sender_id, SUM(coin_total) as total_coins, COUNT(*) as count')
            ->with('sender:id,username,avatar_url,level')
            ->groupBy('sender_id')
            ->orderByDesc('total_coins')
            ->limit(10)
            ->get();

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
