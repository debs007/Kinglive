<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    private string $fcmKey;

    public function __construct()
    {
        $this->fcmKey = config('services.fcm.server_key', '');
    }

    /**
     * Send a push notification to a single user.
     */
    public function sendToUser(int $userId, string $title, string $body, array $data = []): bool
    {
        $user = User::find($userId);
        if (! $user || ! $user->device_token) {
            return false;
        }

        return $this->sendFcm([$user->device_token], $title, $body, $data);
    }

    /**
     * Notify all followers that a host has gone live.
     */
    public function notifyFollowersLive(int $hostId, string $roomId, string $roomTitle): void
    {
        $host = User::with('followers:id,device_token')->find($hostId);
        if (! $host) {
            return;
        }

        $tokens = $host->followers
            ->whereNotNull('device_token')
            ->pluck('device_token')
            ->toArray();

        if (empty($tokens)) {
            return;
        }

        foreach (array_chunk($tokens, 500) as $chunk) {
            $this->sendFcm(
                tokens: $chunk,
                title:  "🔴 {$host->display_name} is LIVE!",
                body:   $roomTitle,
                data:   ['type' => 'host_live', 'room_id' => $roomId, 'host_id' => (string) $hostId]
            );
        }
    }

    /**
     * Notify a user they received a gift.
     */
    public function notifyGiftReceived(int $receiverId, string $senderName, string $giftName, int $quantity): void
    {
        $this->sendToUser(
            userId: $receiverId,
            title:  "🎁 You received a gift!",
            body:   "{$senderName} sent you {$quantity}x {$giftName}",
            data:   ['type' => 'gift_received']
        );
    }

    /**
     * Notify a user their withdrawal request status changed.
     */
    public function notifyWithdrawalUpdate(int $userId, string $status, float $usdAmount): void
    {
        $messages = [
            'approved' => ['title' => '✅ Withdrawal Approved', 'body' => "Your \${$usdAmount} withdrawal has been approved."],
            'paid'     => ['title' => '💰 Payment Sent', 'body' => "Your \${$usdAmount} withdrawal has been paid."],
            'rejected' => ['title' => '❌ Withdrawal Rejected', 'body' => "Your \${$usdAmount} withdrawal was rejected. Diamonds refunded."],
        ];

        if (! isset($messages[$status])) {
            return;
        }

        $this->sendToUser($userId, $messages[$status]['title'], $messages[$status]['body'], ['type' => 'withdrawal_update', 'status' => $status]);
    }

    // ── Private ───────────────────────────────────────────────────────────────

    private function sendFcm(array $tokens, string $title, string $body, array $data = []): bool
    {
        if (empty($this->fcmKey) || empty($tokens)) {
            return false;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => "key={$this->fcmKey}",
                'Content-Type'  => 'application/json',
            ])->post('https://fcm.googleapis.com/fcm/send', [
                'registration_ids' => $tokens,
                'notification'     => [
                    'title' => $title,
                    'body'  => $body,
                    'sound' => 'default',
                ],
                'data'     => $data,
                'priority' => 'high',
            ]);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::error('FCM send error', ['error' => $e->getMessage()]);
            return false;
        }
    }
}
