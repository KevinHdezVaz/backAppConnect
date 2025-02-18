<?php
namespace App\Models;

use App\Models\DailyMatch;
use App\Models\MatchTeamPlayer;
use Illuminate\Database\Eloquent\Model;

class MatchTeam extends Model
{

    protected $table = 'match_teams';


    protected $fillable = [
        'equipo_partido_id',
        'name',
        'color',
        'emoji',
        'player_count',
        'max_players'
    ];

    protected $casts = [
        'player_count' => 'integer',
        'max_players' => 'integer'
    ];

    public function dailyMatch()
    {
        return $this->belongsTo(DailyMatch::class, 'equipo_partido_id');
    }

   
    public function players()
    {
        return $this->hasMany(MatchTeamPlayer::class, 'match_team_id');
    }
    
//public function players()
//{
 //   return $this->hasMany(MatchPlayer::class, 'equipo_partido_id', 'equipo_partido_id');
//}

    public function isFullyBooked()
    {
        return $this->player_count >= $this->max_players;
    }

    public function getRemainingSpots()
    {
        return $this->max_players - $this->player_count;
    }
}