<?php

namespace App\Services;

use App\Events\UserBannedEvent;
use App\Models\User;
use App\Models\UserBan;
use Carbon\Carbon;
use Illuminate\Support\Facades\Redis;

class BanService
{
    /**
     * Ban a user.
     *
     * @param  string  $duration  Examples: "1h", "6h", "24h", "3d", "7d", "30d", "3m", "permanent"
     * @param  string  $type      "global" | "room" | "chat" | "live"
     */
    public function ban(
        int     $targetUserId,
        int     $adminId,
        string  $reason,
        string  $duration,
        string  $type = 'global',
        ?string $roomId = null
    ): UserBan {
        $expiresAt = $this->parseDuration($duration);

        $ban = UserBan::create([
            'user_id'    => $targetUserId,
            'banned_by'  => $adminId,
            'reason'     => $reason,
            'type'       => $type,
            'room_id'    => $roomId,
            'expires_at' => $expiresAt,
            'is_active'  => true,
        ]);

        $this->cacheban($ban);

        event(new UserBannedEvent($targetUserId, $type, $roomId, $reason, $expiresAt));

        return $ban;
    }

    public function unban(int $userId, string $type = 'global', ?string $roomId = null): void
    {
        $query = UserBan::where('user_id', $userId)
            ->where('type', $type)
            ->where('is_active', true);

        if ($type === 'room' && $roomId) {
            $query->where('room_id', $roomId);
        }

        $query->update(['is_active' => false]);

        $this->clearCache($userId, $type, $roomId);
    }

    public function isGloballyBanned(int $userId): bool
    {
        if (Redis::exists("ban:global:{$userId}")) {
            return true;
        }

        $ban = UserBan::where('user_id', $userId)
            ->where('type', 'global')
            ->active()
            ->first();

        if ($ban) {
            $this->cacheBan($ban);
            return true;
        }

        return false;
    }

    public function isRoomBanned(int $userId, string $roomId): bool
    {
        if (Redis::exists("ban:room:{$userId}:{$roomId}")) {
            return true;
        }

        $ban = UserBan::where('user_id', $userId)
            ->where('type', 'room')
            ->where('room_id', $roomId)
            ->active()
            ->first();

        if ($ban) {
            $this->cacheBan($ban);
            return true;
        }

        return false;
    }

    public function getBanHistory(int $userId): \Illuminate\Database\Eloquent\Collection
    {
        return UserBan::with('bannedBy:id,username')
            ->where('user_id', $userId)
            ->latest()
            ->get();
    }

    public function expireOldBans(): int
    {
        $expired = UserBan::where('is_active', true)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->get();

        foreach ($expired as $ban) {
            $ban->update(['is_active' => false]);
            $this->clearCache($ban->user_id, $ban->type, $ban->room_id);
        }

        return $expired->count();
    }

    // ── Private ───────────────────────────────────────────────────────────────

    private function cacheBan(UserBan $ban): void
    {
        $ttl = $ban->expires_at
            ? max(1, now()->diffInSeconds($ban->expires_at))
            : 0;

        if ($ban->type === 'global') {
            $key = "ban:global:{$ban->user_id}";
        } else {
            $key = "ban:room:{$ban->user_id}:{$ban->room_id}";
        }

        $ttl > 0 ? Redis::setex($key, $ttl, $ban->id) : Redis::set($key, $ban->id);
    }

    private function clearCache(int $userId, string $type, ?string $roomId): void
    {
        if ($type === 'global') {
            Redis::del("ban:global:{$userId}");
        } elseif ($type === 'room' && $roomId) {
            Redis::del("ban:room:{$userId}:{$roomId}");
        }
    }

    private function parseDuration(string $duration): ?Carbon
    {
        if ($duration === 'permanent') {
            return null;
        }

        $unit  = substr($duration, -1);
        $value = (int) substr($duration, 0, -1);

        return match ($unit) {
            'h'     => now()->addHours($value),
            'd'     => now()->addDays($value),
            'm'     => now()->addMonths($value),
            default => now()->addHours(1),
        };
    }
}
