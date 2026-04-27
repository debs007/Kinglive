<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoinSellerTransaction extends Model
{
    protected $fillable = [
        'coin_seller_id', 'user_id', 'coins', 'type', 'note',
    ];

    public function seller(): BelongsTo
    {
        return $this->belongsTo(CoinSeller::class, 'coin_seller_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
