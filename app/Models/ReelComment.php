<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReelComment extends Model
{
    use SoftDeletes;

    protected $table    = 'reel_comments';
    protected $fillable = ['reel_id', 'user_id', 'body'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
