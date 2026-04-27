<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReelView extends Model
{
    protected $table    = 'reel_views';
    protected $fillable = ['reel_id', 'user_id'];
}
