<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CoinSeller extends Model
{
    protected $fillable = [
        'name', 'email', 'password',
        'coin_balance', 'total_sold', 'is_active', 'session_token',
        'price_per_100k', 'whatsapp_number',
    ];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return [
            'is_active'      => 'boolean',
            'price_per_100k' => 'decimal:2',
        ];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(CoinSellerTransaction::class);
    }

    public function soldThisMonth(): int
    {
        return $this->transactions()
            ->where('created_at', '>=', now()->startOfMonth())
            ->sum('coins');
    }

    public function soldToday(): int
    {
        return $this->transactions()
            ->where('created_at', '>=', now()->startOfDay())
            ->sum('coins');
    }
}
