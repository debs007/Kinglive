<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Frame extends Model
{
    protected $fillable = [
        'name', 'svga_url', 'thumbnail_url',
        'price', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'price'     => 'integer',
    ];

    public function owners(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_frames')
            ->withPivot('source')
            ->withTimestamps();
    }
}
