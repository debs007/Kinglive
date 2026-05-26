<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserFrame extends Model
{
    protected $fillable = ['user_id', 'frame_id', 'source'];

    public function frame(): BelongsTo
    {
        return $this->belongsTo(Frame::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
