<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Cache;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'username', 'email', 'phone', 'password',
        'avatar_url', 'cover_url', 'frame_url', 'display_name', 'bio',
        'country_code', 'role',
        'coin_balance', 'diamond_balance',
        'level', 'xp',
        'is_verified', 'is_active',
        'total_streams', 'total_diamonds_earned',
        'last_seen_at', 'device_token', 'device_platform',
    ];

    protected $hidden = ['password', 'remember_token', 'device_token'];

    protected function casts(): array
    {
        return [
            'is_verified'  => 'boolean',
            'is_active'    => 'boolean',
            'last_seen_at' => 'datetime',
            'password'     => 'hashed',
        ];
    }

    // ── JWT ──────────────────────────────────────────────────────────────────

    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [];
    }

    // ── Relations ────────────────────────────────────────────────────────────

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class, 'host_user_id');
    }

    public function bans(): HasMany
    {
        return $this->hasMany(UserBan::class);
    }

    public function giftsSent(): HasMany
    {
        return $this->hasMany(GiftTransaction::class, 'sender_id');
    }

    public function giftsReceived(): HasMany
    {
        return $this->hasMany(GiftTransaction::class, 'receiver_id');
    }

    public function coinTransactions(): HasMany
    {
        return $this->hasMany(CoinTransaction::class);
    }

    public function withdrawals(): HasMany
    {
        return $this->hasMany(WithdrawalRequest::class);
    }

    public function gameSessions(): HasMany
    {
        return $this->hasMany(GameSession::class);
    }

    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'follows', 'following_id', 'follower_id')
                    ->withTimestamps();
    }

    public function following(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'follows', 'follower_id', 'following_id')
                    ->withTimestamps();
    }

    // ── Accessors ────────────────────────────────────────────────────────────

    public function getFollowerCountAttribute(): int
    {
        return Cache::remember("user:{$this->id}:followers", 60, fn () => $this->followers()->count());
    }

    public function getFollowingCountAttribute(): int
    {
        return Cache::remember("user:{$this->id}:following", 60, fn () => $this->following()->count());
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    public function isFollowing(int $userId): bool
    {
        return $this->following()->where('following_id', $userId)->exists();
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, ['admin', 'super_admin']);
    }

    public function activeBan(): ?UserBan
    {
        return $this->bans()
            ->where('type', 'global')
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->latest()
            ->first();
    }

    public function toProfileArray(): array
    {
        return [
            'id'              => $this->id,
            'username'        => $this->username,
            'display_name'    => $this->display_name,
            'avatar_url'      => $this->avatar_url,
            'cover_url'       => $this->cover_url ?? null,
            'frame_url'       => $this->frame_url,
            'bio'             => $this->bio,
            'level'           => $this->level,
            'xp'              => $this->xp,
            'is_verified'     => $this->is_verified,
            'coin_balance'    => $this->coin_balance,
            'diamond_balance' => $this->diamond_balance,
            'follower_count'  => $this->follower_count,
            'following_count' => $this->following_count,
            'role'            => $this->role,
            'country_code'    => $this->country_code,
        ];
    }
}
