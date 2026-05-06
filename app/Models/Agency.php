<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Agency extends Model
{
    protected $fillable = [
        'name', 'code', 'email', 'password',
        'logo_url', 'description', 'is_active',
        'owner_id', 'session_token',
        'commission_pct',  // percentage of total diamonds as commission
    ];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return [
            'is_active'      => 'boolean',
            'commission_pct' => 'decimal:2',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(User::class, 'agency_id');
    }

    public function memberCount(): int
    {
        return $this->members()->count();
    }

    public function joinRequests(): HasMany
    {
        return $this->hasMany(AgencyJoinRequest::class);
    }

    public function pendingRequests(): HasMany
    {
        return $this->hasMany(AgencyJoinRequest::class)
                    ->where('status', 'pending');
    }
}
