<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class UserProfile extends Model
{
    protected $fillable = [
        'user_id',
        'position',
        'skill_level',
        'preferred_zone',
        'playing_schedule'
    ];

    protected $casts = [
        'playing_schedule' => 'array'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}