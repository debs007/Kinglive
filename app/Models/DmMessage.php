<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DmMessage extends Model
{
    use SoftDeletes;

    protected $table = 'dm_messages';

    protected $fillable = [
        'conversation_id', 'sender_id', 'type',
        'body', 'gift_id', 'gift_quantity',
        'diamond_value', 'is_read',
    ];

    protected $casts = [
        'is_read'       => 'boolean',
        'gift_quantity' => 'integer',
        'diamond_value' => 'integer',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(DmConversation::class, 'conversation_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function gift(): BelongsTo
    {
        return $this->belongsTo(Gift::class, 'gift_id');
    }

    public function toArray(): array
    {
        return [
            'id'             => $this->id,
            'conversation_id'=> $this->conversation_id,
            'sender_id'      => $this->sender_id,
            'type'           => $this->type,
            'body'           => $this->body,
            'gift'           => $this->gift ? [
                'id'            => $this->gift->id,
                'name'          => $this->gift->name,
                'thumbnail_url' => $this->gift->thumbnail_url,
                'diamond_value' => $this->gift->diamond_value,
            ] : null,
            'gift_quantity'  => $this->gift_quantity,
            'diamond_value'  => $this->diamond_value,
            'is_read'        => $this->is_read,
            'created_at'     => $this->created_at?->toIso8601String(),
        ];
    }
}
