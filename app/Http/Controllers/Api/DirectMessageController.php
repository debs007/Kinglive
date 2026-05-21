<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DmConversation;
use App\Services\NotificationService;
use App\Models\DmMessage;
use App\Models\Gift;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DirectMessageController extends Controller
{
    // ── 1. Get all conversations for current user ─────────────────────────────

    public function conversations(): JsonResponse
    {
        $myId = auth()->id();

        $convs = DmConversation::where('user_one_id', $myId)
            ->orWhere('user_two_id', $myId)
            ->with([
                'userOne:id,username,display_name,avatar_url,is_verified',
                'userTwo:id,username,display_name,avatar_url,is_verified',
                'lastMessage',
            ])
            ->whereNotNull('last_message_at')
            ->orderByDesc('last_message_at')
            ->get();

        $result = $convs->map(function ($conv) use ($myId) {
            $other   = $conv->otherUser($myId);
            $unread  = $conv->unreadFor($myId);
            $last    = $conv->lastMessage;

            return [
                'id'          => $conv->id,
                'other_user'  => [
                    'id'           => $other->id,
                    'username'     => $other->display_name ?? $other->username,
                    'avatar_url'   => $other->avatar_url,
                    'is_verified'  => $other->is_verified,
                ],
                'last_message' => $last ? [
                    'type'       => $last->type,
                    'body'       => match($last->type) {
                                       'text'  => $last->body,
                                       'image' => '📷 Image',
                                       'voice' => '🎤 Voice message',
                                       'gift'  => '🎁 Gift',
                                       default => $last->body,
                                   },
                    'sender_id'  => $last->sender_id,
                    'created_at' => $last->created_at?->toIso8601String(),
                ] : null,
                'unread_count' => $unread,
                'updated_at'   => $conv->last_message_at?->toIso8601String(),
            ];
        });

        return response()->json(['data' => $result]);
    }

    // ── 2. Get or create conversation with a user ─────────────────────────────

    public function findOrCreate(Request $request): JsonResponse
    {
        $userId = (int) $request->input('user_id', 0);
        $myId   = auth()->id();

        if (! $userId) {
            return response()->json(['message' => 'user_id is required'], 422);
        }

        if ($myId === $userId) {
            return response()->json(['message' => 'Cannot chat with yourself'], 422);
        }

        $other = User::findOrFail($userId);
        $conv  = DmConversation::findOrCreateBetween($myId, $userId);

        return response()->json([
            'conversation_id' => $conv->id,
            'other_user' => [
                'id'          => $other->id,
                'username'    => $other->display_name ?? $other->username,
                'avatar_url'  => $other->avatar_url,
                'is_verified' => $other->is_verified,
            ],
        ]);
    }

    // ── 3. Get messages in a conversation ─────────────────────────────────────

    public function messages(int $conversationId, Request $request): JsonResponse
    {
        $myId = auth()->id();
        $conv = DmConversation::findOrFail($conversationId);

        // Ensure current user is part of this conversation
        if ($conv->user_one_id !== $myId && $conv->user_two_id !== $myId) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $messages = DmMessage::where('conversation_id', $conversationId)
            ->with('gift:id,name,thumbnail_url,diamond_value')
            ->orderByDesc('created_at')
            ->paginate(50);

        // Mark messages as read
        DmMessage::where('conversation_id', $conversationId)
            ->where('sender_id', '!=', $myId)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        // Reset unread count for current user
        $isOne = $conv->user_one_id === $myId;
        $conv->update($isOne ? ['unread_one' => 0] : ['unread_two' => 0]);

        return response()->json([
            'data' => $messages->getCollection()->map(fn ($m) => $m->toArray()),
            'meta' => [
                'current_page' => $messages->currentPage(),
                'last_page'    => $messages->lastPage(),
                'total'        => $messages->total(),
            ],
        ]);
    }

    // ── 4. Send a text message ────────────────────────────────────────────────

    public function sendText(int $conversationId, Request $request): JsonResponse
    {
        $request->validate(['body' => ['required', 'string', 'max:1000']]);

        return $this->_send($conversationId, 'text', $request->body);
    }

    // ── 5. Get presigned URL for image upload ─────────────────────────────────

    public function imageUploadUrl(Request $request): JsonResponse
    {
        $userId = auth()->id();
        $ext    = $request->input('ext', 'jpg');
        $key    = "dm/images/user_{$userId}_" . Str::random(12) . ".{$ext}";

        $client = new \Aws\S3\S3Client([
            'version'     => 'latest',
            'region'      => config('filesystems.disks.s3.region'),
            'credentials' => [
                'key'    => config('filesystems.disks.s3.key'),
                'secret' => config('filesystems.disks.s3.secret'),
            ],
        ]);

        $cmd       = $client->getCommand('PutObject', [
            'Bucket' => config('filesystems.disks.s3.bucket'),
            'Key'    => $key,
        ]);
        $presigned = $client->createPresignedRequest($cmd, '+15 minutes');

        return response()->json([
            'upload_url' => (string) $presigned->getUri(),
            'cdn_url'    => \Storage::disk('s3')->url($key),
        ]);
    }

    // ── 6. Send an image message ──────────────────────────────────────────────

    public function sendImage(int $conversationId, Request $request): JsonResponse
    {
        $request->validate(['image_url' => ['required', 'string', 'url']]);

        return $this->_send($conversationId, 'image', $request->image_url);
    }

    // ── 7. Send a gift message ────────────────────────────────────────────────

    public function sendGift(int $conversationId, Request $request): JsonResponse
    {
        $request->validate([
            'gift_id'  => ['required', 'exists:gifts,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:99'],
        ]);

        $myId    = auth()->id();
        $conv    = DmConversation::findOrFail($conversationId);

        if ($conv->user_one_id !== $myId && $conv->user_two_id !== $myId) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $gift         = Gift::findOrFail($request->gift_id);
        $quantity     = (int) $request->quantity;
        $totalCoins   = $gift->coin_price * $quantity;
        $totalDiamonds = ($gift->diamond_value ?? 0) * $quantity;

        $receiverId = $conv->user_one_id === $myId
            ? $conv->user_two_id
            : $conv->user_one_id;

        return DB::transaction(function () use (
            $myId, $receiverId, $conv, $gift,
            $quantity, $totalCoins, $totalDiamonds, $conversationId
        ) {
            $sender = User::lockForUpdate()->find($myId);

            if ($sender->coin_balance < $totalCoins) {
                return response()->json(['message' => 'Not enough coins'], 422);
            }

            // Deduct coins from sender
            $sender->decrement('coin_balance', $totalCoins);

            // Add diamonds to receiver
            User::where('id', $receiverId)->increment('diamond_balance', $totalDiamonds);

            // Save message (no GiftTransaction — room_id is required there, DM gifts have no room)
            $message = DmMessage::create([
                'conversation_id' => $conversationId,
                'sender_id'       => $myId,
                'type'            => 'gift',
                'gift_id'         => $gift->id,
                'gift_quantity'   => $quantity,
                'diamond_value'   => $totalDiamonds,
                'is_read'         => false,
            ]);

            $message->load('gift:id,name,thumbnail_url,diamond_value');
            $this->_updateConversation($conv, $message, $myId);

            // Push WS event to receiver
            $this->_pushWsToReceiver($receiverId, $message->toArray());

            return response()->json([
                'message'      => $message->toArray(),
                'coins_spent'  => $totalCoins,
                'new_balance'  => $sender->fresh()->coin_balance,
            ]);
        });
    }

    // ── 8. Total unread count across all conversations ────────────────────────

    public function unreadCount(): JsonResponse
    {
        $myId = auth()->id();

        $count = DmConversation::where('user_one_id', $myId)
                ->sum('unread_one')
            + DmConversation::where('user_two_id', $myId)
                ->sum('unread_two');

        return response()->json(['unread_count' => (int) $count]);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function _pushWsToReceiver(int $receiverId, array $message): void
    {
        try {
            // Queue in Redis — WS process picks it up on next ping
            \App\Swoole\WebSocketHandler::queueDmForUser($receiverId, $message);
        } catch (\Throwable $e) {
            // Best-effort — don't fail the request
        }
    }

    private function _send(int $conversationId, string $type, string $body): JsonResponse
    {
        $myId = auth()->id();
        $conv = DmConversation::findOrFail($conversationId);

        if ($conv->user_one_id !== $myId && $conv->user_two_id !== $myId) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $message = DmMessage::create([
            'conversation_id' => $conversationId,
            'sender_id'       => $myId,
            'type'            => $type,
            'body'            => $body,
            'is_read'         => false,
        ]);

        $this->_updateConversation($conv, $message, $myId);

        // Push WS event to receiver in real time
        $receiverId = $conv->user_one_id === $myId ? $conv->user_two_id : $conv->user_one_id;
        $this->_pushWsToReceiver($receiverId, $message->toArray());

        // Push notification (for when receiver's app is backgrounded/closed)
        $sender = auth()->user();
        app(NotificationService::class)->sendToUser(
            userId: $receiverId,
            title:  $sender->display_name ?? $sender->username,
            body:   $type === 'text' ? $body : '📷 Sent you a message',
            data:   [
                'type'            => 'dm_message',
                'conversation_id' => (string) $conversationId,
                'sender_id'       => (string) $myId,
            ]
        );

        return response()->json(['message' => $message->toArray()]);
    }

    private function _updateConversation(DmConversation $conv, DmMessage $msg, int $senderId): void
    {
        $isOne      = $conv->user_one_id === $senderId;
        $unreadCol  = $isOne ? 'unread_two' : 'unread_one';

        $conv->update([
            'last_message_id' => $msg->id,
            'last_message_at' => now(),
            $unreadCol        => DB::raw("{$unreadCol} + 1"),
        ]);
    }
}