<?php

namespace App\Models;

use App\Models\Field;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EquipoPartido extends Model
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
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'player_count' => 'integer',
        'max_players' => 'integer',
        'price' => 'decimal:2'
    ];

    public function field(): BelongsTo
    {
        return $this->belongsTo(Field::class);
    }

    public function players(): HasMany
    {
        return $this->hasMany(MatchPlayer::class, 'equipo_partido_id');
    }

    public function partido(): BelongsTo
    {
        return $this->belongsTo(Partido::class);
    }

    public function getAvailableSpotsAttribute()
    {
        return $this->max_players - $this->player_count;
    }

    public function scopeAvailable($query)
    {
        return $query->where('status', 'open')
                    ->where('schedule_date', '>=', now()->format('Y-m-d'))
                    ->whereRaw('player_count < max_players');
    }
}