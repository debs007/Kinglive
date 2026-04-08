<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomSeat extends Model
{
    protected $fillable = [
        'room_id', 'user_id', 'seat_index', 'is_muted', 'is_locked',
    ];

    protected function casts(): array
    {
        return [
            'is_muted'  => 'boolean',
            'is_locked' => 'boolean',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
