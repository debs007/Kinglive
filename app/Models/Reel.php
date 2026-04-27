<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Reel extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id', 'title', 'description',
        'video_url', 'thumbnail_url', 'music_title',
        'view_count', 'like_count', 'comment_count',
        'diamond_earned', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function views(): HasMany
    {
        return $this->hasMany(ReelView::class);
    }

    public function likes(): HasMany
    {
        return $this->hasMany(ReelLike::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(ReelComment::class)->latest();
    }

    public function toFeedArray(int $authId): array
    {
        return [
            'id'            => $this->id,
            'title'         => $this->title,
            'description'   => $this->description,
            'video_url'     => $this->video_url,
            'thumbnail_url' => $this->thumbnail_url,
            'music_title'   => $this->music_title,
            'view_count'    => $this->view_count,
            'like_count'    => $this->like_count,
            'comment_count' => $this->comment_count,
            'is_liked'      => $this->likes()->where('user_id', $authId)->exists(),
            'created_at'    => $this->created_at?->toIso8601String(),
            'user'          => [
                'id'         => $this->user->id,
                'username'   => $this->user->display_name ?? $this->user->username,
                'avatar_url' => $this->user->avatar_url,
                'is_verified'=> $this->user->is_verified,
            ],
        ];
    }
}
