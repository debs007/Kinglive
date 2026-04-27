<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReelLike extends Model
{
    protected $table    = 'reel_likes';
    protected $fillable = ['reel_id', 'user_id'];
}
