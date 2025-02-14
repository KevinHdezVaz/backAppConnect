<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyMatch extends Model
{
    protected $table = 'equipo_partidos';

    protected $fillable = [
        'name',
        'player_count',
        'partido_id',
        'max_players',
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

    // Relaciones
    public function field()
    {
        return $this->belongsTo(Field::class, 'field_id');
    }

    // DailyMatch.php
public function isFullyBooked()
{
    return $this->player_count >= $this->max_players;
}

public function canJoin(User $user)
{
    return !$this->isFullyBooked() && 
           !$this->players()->where('player_id', $user->id)->exists();
}

public function getRemainingSpots()
{
    return $this->max_players - $this->player_count;
}

    public function partido()
    {
        return $this->belongsTo(Partido::class, 'partido_id');
    }

    // Modificadores de atributos para manejar los tiempos
    public function setStartTimeAttribute($value)
    {
        if ($value instanceof \DateTime) {
            $this->attributes['start_time'] = $value->format('H:i:s');
        } else {
            $this->attributes['start_time'] = date('H:i:s', strtotime($value));
        }
    }

    public function setEndTimeAttribute($value)
    {
        if ($value instanceof \DateTime) {
            $this->attributes['end_time'] = $value->format('H:i:s');
        } else {
            $this->attributes['end_time'] = date('H:i:s', strtotime($value));
        }
    }
}