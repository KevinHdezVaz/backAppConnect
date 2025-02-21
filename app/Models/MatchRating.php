<?php

namespace App\Models;

use App\Models\User;
use App\Models\DailyMatch;
use Illuminate\Database\Eloquent\Model;

class MatchRating extends Model
{
    protected $fillable = [
        'match_id',
        'rated_user_id',
        'rater_user_id',
        'rating',
        'comment',
        'mvp_vote'
    ];

    protected $casts = [
        'mvp_vote' => 'boolean',
        'rating' => 'integer'
    ];

    public function match()
    {
        return $this->belongsTo(DailyMatch::class, 'match_id');
    }

    public function ratedUser()
    {
        return $this->belongsTo(User::class, 'rated_user_id');
    }

    public function rater()
    {
        return $this->belongsTo(User::class, 'rater_user_id');
    }
}