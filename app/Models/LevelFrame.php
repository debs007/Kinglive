<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LevelFrame extends Model
{
    protected $fillable = [
        'min_level', 'max_level', 'name',
        'svga_url', 'thumbnail_url', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    /**
     * Get the frame for a given level.
     * Returns the highest min_level frame that the user qualifies for.
     */
    public static function forLevel(int $level): ?self
    {
        return static::where('is_active', true)
            ->where('min_level', '<=', $level)
            ->where(function ($q) use ($level) {
                $q->whereNull('max_level')
                  ->orWhere('max_level', '>=', $level);
            })
            ->orderByDesc('min_level')
            ->first();
    }
}
