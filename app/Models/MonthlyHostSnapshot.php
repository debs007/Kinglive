<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonthlyHostSnapshot extends Model
{
    public $timestamps  = false;
    protected $fillable = [
        'year', 'month', 'period_start', 'period_end',
        'user_id', 'username', 'display_name', 'email', 'phone',
        'agency_id', 'agency_name', 'agency_commission_pct',
        'diamonds_earned', 'diamond_balance',
        'total_live_minutes', 'total_live_hours',
        'video_live_days', 'audio_live_days', 'total_streams',
        'usd_amount', 'commission_usd', 'net_usd',
        'created_at',
    ];

    protected $casts = [
        'period_start'         => 'date',
        'period_end'           => 'date',
        'usd_amount'           => 'decimal:2',
        'commission_usd'       => 'decimal:2',
        'net_usd'              => 'decimal:2',
        'agency_commission_pct'=> 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }
}
