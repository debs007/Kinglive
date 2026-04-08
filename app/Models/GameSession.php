<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameSession extends Model
{
    protected $fillable = [
        'user_id', 'room_id', 'game_url', 'game_id',
        'game_data', 'coins_spent', 'coins_won',
        'status', 'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'game_data' => 'array',
            'ended_at'  => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function getNetCoinsAttribute(): int
    {
        return $this->coins_won - $this->coins_spent;
    }
}
