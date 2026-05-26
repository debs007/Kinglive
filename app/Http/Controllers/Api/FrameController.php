<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Frame;
use App\Models\UserFrame;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FrameController extends Controller
{
    // ── Shop: list all active frames with owned flag ───────────────────────────

    public function shop(): JsonResponse
    {
        $userId    = auth()->id();
        $ownedIds  = UserFrame::where('user_id', $userId)
            ->pluck('frame_id')
            ->toArray();

        $frames = Frame::where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($frame) => [
                'id'            => $frame->id,
                'name'          => $frame->name,
                'svga_url'      => $frame->svga_url,
                'thumbnail_url' => $frame->thumbnail_url,
                'price'         => $frame->price,
                'owned'         => in_array($frame->id, $ownedIds),
            ]);

        return response()->json($frames);
    }

    // ── Inventory: frames owned by user ───────────────────────────────────────

    public function inventory(): JsonResponse
    {
        $userId = auth()->id();
        $user   = auth()->user();

        $frames = UserFrame::where('user_id', $userId)
            ->with('frame')
            ->get()
            ->filter(fn ($uf) => $uf->frame && $uf->frame->is_active)
            ->map(fn ($uf) => [
                'id'            => $uf->frame->id,
                'name'          => $uf->frame->name,
                'svga_url'      => $uf->frame->svga_url,
                'thumbnail_url' => $uf->frame->thumbnail_url,
                'is_applied'    => $user->frame_url === $uf->frame->svga_url,
            ])
            ->values();

        return response()->json($frames);
    }

    // ── Buy frame from shop ────────────────────────────────────────────────────

    public function buy(Request $request): JsonResponse
    {
        $request->validate(['frame_id' => ['required', 'exists:frames,id']]);

        $user  = auth()->user();
        $frame = Frame::findOrFail($request->frame_id);

        if (! $frame->is_active) {
            return response()->json(['message' => 'Frame not available.'], 404);
        }

        // Check not already owned
        if (UserFrame::where('user_id', $user->id)
            ->where('frame_id', $frame->id)->exists()) {
            return response()->json(['message' => 'Already owned.', 'code' => 'already_owned'], 400);
        }

        // Check coins balance
        if ($frame->price > 0 && $user->coin_balance < $frame->price) {
            return response()->json(['message' => 'Insufficient coins.', 'code' => 'insufficient_coins'], 400);
        }

        // Deduct coins if needed
        if ($frame->price > 0) {
            $user->decrement('coin_balance', $frame->price);

            \App\Models\CoinTransaction::create([
                'user_id'       => $user->id,
                'type'          => 'frame_purchase',
                'amount'        => -$frame->price,
                'balance_after' => $user->fresh()->coin_balance,
                'reference'     => "frame_purchase:{$frame->id}",
            ]);
        }

        UserFrame::create([
            'user_id'  => $user->id,
            'frame_id' => $frame->id,
            'source'   => 'shop',
        ]);

        return response()->json([
            'message'      => 'Frame purchased.',
            'coin_balance' => $user->fresh()->coin_balance,
        ]);
    }

    // ── Apply or remove frame ──────────────────────────────────────────────────

    public function apply(Request $request): JsonResponse
    {
        $request->validate(['frame_id' => ['nullable', 'exists:frames,id']]);

        $user = auth()->user();

        // null frame_id = remove frame
        if (! $request->frame_id) {
            $user->update(['frame_url' => null]);
            return response()->json(['message' => 'Frame removed.', 'frame_url' => null]);
        }

        // Check ownership
        $owned = UserFrame::where('user_id', $user->id)
            ->where('frame_id', $request->frame_id)
            ->exists();

        if (! $owned) {
            return response()->json(['message' => 'Frame not in inventory.'], 403);
        }

        $frame = Frame::findOrFail($request->frame_id);
        $user->update(['frame_url' => $frame->svga_url]);

        return response()->json([
            'message'   => 'Frame applied.',
            'frame_url' => $frame->svga_url,
        ]);
    }
}
