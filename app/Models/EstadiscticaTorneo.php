<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class EstadisticaTorneo extends Model
{
    protected $table = 'estadisticas_torneo';

    protected $fillable = [
        'torneo_id',
        'equipo_id',
        'jugados',
        'ganados',
        'empatados',
        'perdidos',
        'goles_favor',
        'goles_contra',
        'diferencia_goles',
        'puntos',
        'posicion'
    ];

    public function torneo()
    {
        return $this->belongsTo(Torneo::class);
    }

    public function equipo()
    {
        return $this->belongsTo(Equipo::class);
    }
}