<?php
namespace App\Models;

use App\Models\Booking;
use App\Models\ChatMensaje;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable {
    use HasApiTokens, HasFactory, Notifiable;

    public function profile()
    {
        return $this->hasOne(UserProfile::class);
    }

    protected $fillable = [
        'name', 'email', 'phone', 'codigo_postal', 'posicion', 'profile_image', // Asegúrate de que los campos estén en $fillable
    ];


    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'verified' => 'boolean',
    ];


    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function matchEvents()
    {
        return $this->hasMany(MatchEvent::class);
    }

    
 public function bookings()
{
    return $this->hasMany(Booking::class);
}

 
public function equipos()
{
    return $this->belongsToMany(Equipo::class, 'equipo_usuarios')
                ->withPivot(['rol', 'estado', 'posicion'])
                ->withTimestamps();
}


// app/Models/User.php
public function mensajes()
{
    return $this->hasMany(ChatMensaje::class);
}
 public function equipoActual()
{
    return $this->belongsToMany(Equipo::class, 'equipo_usuarios')
                ->wherePivot('estado', 'activo')
                ->first();
}

public function perteneceAEquipo()
{
    return $this->equipos()
                ->wherePivot('estado', 'activo')
                ->exists();
}


public function equiposCapitan()
{
    return $this->equipos()->wherePivot('rol', 'capitan');
}
}
