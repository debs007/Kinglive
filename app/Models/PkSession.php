<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PkSession extends Model
{
    use HasUuids;

    protected $fillable = [
        'room_a_id', 'room_b_id',
        'score_a', 'score_b',
        'winner_room_id', 'status',
        'duration_seconds', 'started_at', 'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at'   => 'datetime',
        ];
    }

    public function roomA(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'room_a_id');
    }

    public function roomB(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'room_b_id');
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'winner_room_id');
    }
}
