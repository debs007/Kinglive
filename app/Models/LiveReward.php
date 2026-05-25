<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LiveReward extends Model
{
    public $timestamps  = false;
    protected $fillable = ['user_id', 'reward_date', 'room_id', 'amount', 'credited_at'];
    protected $casts    = ['reward_date' => 'date', 'credited_at' => 'datetime'];
}
