<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WithdrawalRequest extends Model
{
    protected $fillable = [
        'user_id', 'diamond_amount', 'usd_amount',
        'payment_method', 'payment_details',
        'status', 'reviewed_by', 'admin_note', 'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'payment_details' => 'array',
            'processed_at'    => 'datetime',
            'usd_amount'      => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}
