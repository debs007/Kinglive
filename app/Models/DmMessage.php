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
        // For voice messages, decode body JSON to get url and duration separately
        $body          = $this->body;
        $voiceDuration = null;

        if ($this->type === 'voice' && is_string($body) && str_starts_with($body, '{')) {
            $decoded = json_decode($body, true);
            if (is_array($decoded)) {
                $body          = $decoded['url']      ?? $body;
                $voiceDuration = $decoded['duration'] ?? null;
            }
        }

        return [
            'id'              => (int) $this->id,
            'conversation_id' => (int) $this->conversation_id,
            'sender_id'       => (int) $this->sender_id,
            'type'            => $this->type              ?? 'text',
            'body'            => $body,
            'voice_duration'  => $voiceDuration,
            'gift'            => $this->gift ? [
                'id'            => $this->gift->id,
                'name'          => $this->gift->name,
                'thumbnail_url' => $this->gift->thumbnail_url,
                'diamond_value' => $this->gift->diamond_value,
            ] : null,
            'gift_quantity'  => (int) ($this->gift_quantity ?? 0),
            'diamond_value'  => (int) ($this->diamond_value ?? 0),
            'is_read'        => (bool) ($this->is_read ?? false),
            'created_at'     => ($this->created_at ?? now())->toIso8601String(),
        ];
    }
}