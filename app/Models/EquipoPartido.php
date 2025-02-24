<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EquipoPartido extends Model
{
    protected $table = 'equipo_partidos';
    
    protected $fillable = [
        'name',
        'player_count',
        'max_players',
        'field_id',
        'partido_id',
        'schedule_date',
        'start_time',
        'end_time',
        'price',
        'status'
    ];

    protected $casts = [
        'schedule_date' => 'date',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'price' => 'decimal:2'
    ];

    public function teams()
    {
        return $this->hasMany(MatchTeam::class, 'equipo_partido_id');
    }

    public function field()
    {
        return $this->belongsTo(Field::class);
    }
}