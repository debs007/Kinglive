<?php

namespace App\Services;

use App\Models\User;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    private $projectId;
    private $credentialsPath;

    public function __construct()
    {
        $this->projectId       = (string) config('services.fcm.project_id', '');
        $this->credentialsPath = (string) config('services.fcm.credentials_path',
            storage_path('app/firebase-credentials.json'));

        Log::info('NotificationService init', [
            'project_id'       => $this->projectId,
            'credentials_path' => $this->credentialsPath,
            'file_exists'      => file_exists($this->credentialsPath),
        ]);
    }

    public function sendToUser(int $userId, string $title, string $body, array $data = []): bool
    {
        Log::info('FCM sendToUser called', ['user_id' => $userId, 'title' => $title]);

        $user = User::find($userId);
        if (! $user) {
            Log::warning('FCM: user not found', ['user_id' => $userId]);
            return false;
        }
        if (! $user->device_token) {
            Log::warning('FCM: user has no device_token', ['user_id' => $userId]);
            return false;
        }

        Log::info('FCM: sending to token', [
            'user_id' => $userId,
            'token'   => substr($user->device_token, 0, 20) . '...',
        ]);

        return $this->sendFcm([$user->device_token], $title, $body, $data);
    }

    public function notifyFollowersLive(int $hostId, string $roomId, string $title): void
    {
        $host = User::with('followers:id,device_token')->find($hostId);
        if (! $host) return;

        $tokens = $host->followers
            ->whereNotNull('device_token')
            ->pluck('device_token')
            ->filter()
            ->values()
            ->toArray();

        Log::info('FCM notifyFollowersLive', [
            'host_id'      => $hostId,
            'token_count'  => count($tokens),
        ]);

        if (empty($tokens)) return;

        $this->sendFcm(
            $tokens,
            ($host->display_name ?? $host->username) . ' is LIVE! 🔴',
            $title ?: 'Join the stream now',
            ['type' => 'host_live', 'room_id' => $roomId]
        );
    }

    public function notifyWithdrawalStatus(int $userId, string $status): void
    {
        $messages = [
            'approved' => ['title' => 'Withdrawal Approved ✅', 'body' => 'Your withdrawal has been approved.'],
            'rejected' => ['title' => 'Withdrawal Rejected ❌', 'body' => 'Your withdrawal was rejected.'],
        ];
        if (! isset($messages[$status])) return;

        $this->sendToUser(
            $userId,
            $messages[$status]['title'],
            $messages[$status]['body'],
            ['type' => 'withdrawal_update', 'status' => $status]
        );
    }

    private function getAccessToken(): string
    {
        if (! file_exists($this->credentialsPath)) {
            Log::error('FCM: credentials file not found at ' . $this->credentialsPath);
            return '';
        }

        try {
            Log::info('FCM: fetching access token from credentials');
            $credentials = new ServiceAccountCredentials(
                'https://www.googleapis.com/auth/firebase.messaging',
                $this->credentialsPath
            );
            $token = $credentials->fetchAuthToken();
            $accessToken = $token['access_token'] ?? '';
            Log::info('FCM: access token fetched', [
                'token_length' => strlen($accessToken),
                'has_token'    => ! empty($accessToken),
            ]);
            return $accessToken;
        } catch (\Throwable $e) {
            Log::error('FCM getAccessToken error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return '';
        }
    }

    /**
     * Send data-only FCM — no system notification shown, always fires onMessage in foreground.
     * Use for in-app DM notifications so Flutter's FirebaseMessaging.onMessage handles it.
     */
    public function sendDataOnlyToUser(int $userId, array $data): bool
    {
        $user = User::find($userId);
        if (! $user?->device_token) return false;
        return $this->sendFcmDataOnly([$user->device_token], $data);
    }

    private function sendFcmDataOnly(array $tokens, array $data): bool
    {
        if (empty($this->projectId) || empty($tokens)) return false;
        $accessToken = $this->getAccessToken();
        if (empty($accessToken)) return false;

        $url = "https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send";
        $success = true;

        foreach ($tokens as $token) {
            try {
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type'  => 'application/json',
                ])->post($url, [
                    'message' => [
                        'token'   => $token,
                        // NO notification field — data-only, always hits onMessage
                        'android' => ['priority' => 'high'],
                        'apns'    => [
                            'headers' => ['apns-priority' => '10'],
                            'payload' => ['aps' => ['content-available' => 1]],
                        ],
                        'data' => array_map('strval', $data),
                    ],
                ]);
                if (! $response->successful()) $success = false;
            } catch (\Throwable $e) {
                Log::error('FCM data-only error: ' . $e->getMessage());
                $success = false;
            }
        }
        return $success;
    }

    private function sendFcm(array $tokens, string $title, string $body, array $data = []): bool
    {
        Log::info('FCM sendFcm called', [
            'project_id'  => $this->projectId,
            'token_count' => count($tokens),
            'title'       => $title,
        ]);

        if (empty($this->projectId) || empty($tokens)) {
            Log::warning('FCM: missing project_id or no tokens', [
                'project_id'  => $this->projectId,
                'token_count' => count($tokens),
            ]);
            return false;
        }

        $accessToken = $this->getAccessToken();
        if (empty($accessToken)) {
            Log::error('FCM: could not get access token');
            return false;
        }

        $url     = "https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send";
        $success = true;

        foreach ($tokens as $token) {
            try {
                Log::info('FCM: posting to FCM v1', ['url' => $url]);

                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type'  => 'application/json',
                ])->post($url, [
                    'message' => [
                        'token'        => $token,
                        'notification' => [
                            'title' => $title,
                            'body'  => $body,
                        ],
                        'android' => [
                            'priority'     => 'high',
                            'notification' => [
                                'sound'        => 'default',
                                'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                            ],
                        ],
                        'apns' => [
                            'payload' => [
                                'aps' => [
                                    'sound' => 'default',
                                    'badge' => 1,
                                ],
                            ],
                        ],
                        'data' => array_map('strval', $data),
                    ],
                ]);

                Log::info('FCM v1 response', [
                    'status'   => $response->status(),
                    'body'     => $response->body(),
                    'success'  => $response->successful(),
                ]);

                if (! $response->successful()) {
                    $success = false;
                }
            } catch (\Throwable $e) {
                Log::error('FCM token send error: ' . $e->getMessage());
                $success = false;
            }
        }

        return $success;
    }
}