<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Equipo extends Model
{
    protected $fillable = [
        'nombre',
        'logo',
        'color_uniforme',
        'nombre_capitan',
        'telefono_capitan',
        'email_capitan'
    ];

    public function jugadores()
    {
        return $this->hasMany(User::class);
    }

    public function torneos()
    {
        return $this->belongsToMany(Torneo::class, 'torneo_equipos')
                    ->withPivot('estado', 'pago_confirmado');
    }
}