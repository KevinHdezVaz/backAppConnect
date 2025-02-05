<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Equipo extends Model
{
    protected $fillable = [
        'nombre',
        'logo',
        'color_uniforme'
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function miembros(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'equipo_usuarios')
                    ->withPivot('rol', 'estado')
                    ->withTimestamps();
    }

    public function torneos(): BelongsToMany
    {
        return $this->belongsToMany(Torneo::class, 'torneo_equipos')
                    ->withPivot('estado', 'pago_confirmado')
                    ->withTimestamps();
    }
 
    public function miembrosActivos()
{
    return $this->miembros()
                ->wherePivot('estado', 'activo');
}

public function cantidadMiembros()
{
    return $this->miembrosActivos()->count();
}

    public function esCapitan(User $user): bool
    {
        return $this->miembros()
                    ->wherePivot('rol', 'capitan')
                    ->where('users.id', $user->id)
                    ->exists();
    }
}