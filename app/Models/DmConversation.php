<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DmConversation extends Model
{
    protected $table = 'dm_conversations';

    protected $fillable = [
        'user_one_id', 'user_two_id',
        'last_message_id', 'last_message_at',
        'unread_one', 'unread_two',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    public function userOne(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_one_id');
    }

    public function userTwo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_two_id');
    }

    public function lastMessage(): BelongsTo
    {
        return $this->belongsTo(DmMessage::class, 'last_message_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(DmMessage::class, 'conversation_id');
    }

    // Get the other user in the conversation
    public function otherUser(int $myId): User
    {
        return $this->user_one_id === $myId ? $this->userTwo : $this->userOne;
    }

    // Get unread count for a specific user
    public function unreadFor(int $userId): int
    {
        return $this->user_one_id === $userId
            ? $this->unread_one
            : $this->unread_two;
    }

    // Find or create conversation between two users
    public static function findOrCreateBetween(int $userA, int $userB): self
    {
        // Always store with lower id as user_one for consistency
        [$one, $two] = $userA < $userB ? [$userA, $userB] : [$userB, $userA];

        return self::firstOrCreate(
            ['user_one_id' => $one, 'user_two_id' => $two]
        );
    }
}
