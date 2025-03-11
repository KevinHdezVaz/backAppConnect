<?php
namespace App\Models;

use App\Models\Wallet;
use App\Models\Booking;
use App\Models\ChatMensaje;
use App\Models\MatchTeamPlayer;
use App\Models\WalletTransaction;
use Laravel\Sanctum\HasApiTokens;
use App\Models\ProfileVerification;
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
        'name',
        'email',
        'password',
        'phone',
        'codigo_postal',
        'posicion',
        'profile_image',
        'referral_code', // Agregado
        'invite_code',   // Agregado
        'is_verified', // Agregar este campo

    ];


    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'verified' => 'boolean',
        'is_verified' => 'boolean', // Asegúrate de que esté casteado como booleano

    ];

    public function matchTeamPlayers()
    {
        return $this->hasMany(MatchTeamPlayer::class, 'user_id');
    }
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

public function profileVerifications()
{
    return $this->hasMany(ProfileVerification::class);
}

public function wallet()
    {
        return $this->hasOne(Wallet::class);
    }

// Método de utilidad para el monedero
public function getWalletBalance()
{
    return $this->wallet->balance ?? 0;
}

public function createWalletIfNotExists()
{
    if (!$this->wallet) {
        return $this->wallet()->create([
            'balance' => 0,
            'status' => 'active'
        ]);
    }
    return $this->wallet;
}

 
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
