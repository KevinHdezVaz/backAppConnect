<?php

namespace App\Models;

use App\Models\User;
use App\Models\Field;
use App\Models\Partido;
use App\Models\MatchTeam;
use App\Models\MatchPlayer;
use App\Models\MatchRating;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyMatch extends Model
{
    protected $table = 'equipo_partidos';
    
    protected $fillable = [
        'name',
        'player_count',
        'partido_id',
        'max_players',
        'game_type',   
        'field_id',
        'schedule_date',
        'start_time',
        'end_time',
        'price',
        'status'
    ];

    protected $casts = [
        'schedule_date' => 'date',
        'price' => 'decimal:2',
        'player_count' => 'integer',
        'max_players' => 'integer'
    ];

  
    
    public function field()
    {
        return $this->belongsTo(Field::class);
    }

    public function teams(): HasMany
    {
        return $this->hasMany(MatchTeam::class, 'equipo_partido_id');
    }

    public function players(): HasMany
    {
        return $this->hasMany(MatchPlayer::class, 'match_id');
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(MatchRating::class, 'match_id');
    }

    public function partido(): BelongsTo
    {
        return $this->belongsTo(Partido::class, 'partido_id');
    }

    // Métodos de utilidad
    public function isFullyBooked(): bool
    {
        return $this->player_count >= $this->max_players;
    }

    public function canJoin(User $user): bool
    {
        return !$this->isFullyBooked() && 
               !$this->players()->where('player_id', $user->id)->exists();
    }

    public function getRemainingSpots(): int
    {
        return $this->max_players - $this->player_count;
    }

    // Modificadores de atributos para manejar los tiempos
    public function setStartTimeAttribute($value): void
    {
        if ($value instanceof \DateTime) {
            $this->attributes['start_time'] = $value->format('H:i:s');
        } else {
            $this->attributes['start_time'] = date('H:i:s', strtotime($value));
        }
    }
    
    public function setEndTimeAttribute($value): void
    {
        if ($value instanceof \DateTime) {
            $this->attributes['end_time'] = $value->format('H:i:s');
        } else {
            $this->attributes['end_time'] = date('H:i:s', strtotime($value));
        }
    }
}