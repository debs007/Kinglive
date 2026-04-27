<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Agency extends Model
{
    protected $fillable = ['name', 'code', 'email', 'password', 'logo_url', 'description', 'is_active', 'owner_id', 'session_token'];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
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

    public function joinRequests(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\AgencyJoinRequest::class);
    }

    public function pendingRequests(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\AgencyJoinRequest::class)
                    ->where('status', 'pending');
    }
}
