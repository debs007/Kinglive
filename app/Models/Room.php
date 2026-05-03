<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Room extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'host_user_id', 'title', 'thumbnail_url', 'type', 'status',
        'viewer_count', 'max_viewers', 'seat_count',
        'is_password_protected', 'password', 'category',
        'agora_channel_id', 'agora_token',
        'started_at', 'ended_at',
        'total_gifts_received', 'peak_viewer_count',
        'current_bg_url',
    ];

    protected $hidden = ['agora_token', 'password'];

    protected function casts(): array
    {
        return [
            'is_password_protected' => 'boolean',
            'started_at'            => 'datetime',
            'ended_at'              => 'datetime',
        ];
    }

    // ── Relations ────────────────────────────────────────────────────────────

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_user_id');
    }

    public function seats(): HasMany
    {
        return $this->hasMany(RoomSeat::class);
    }

    public function giftTransactions(): HasMany
    {
        return $this->hasMany(GiftTransaction::class);
    }

    public function chatMessages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }

    public function gameSessions(): HasMany
    {
        return $this->hasMany(GameSession::class);
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    public function scopeLive($query)
    {
        return $query->where('status', 'live');
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    public function isLive(): bool
    {
        return $this->status === 'live';
    }
}
