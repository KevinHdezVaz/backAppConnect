<?php

namespace App\Models;

use App\Models\Booking;
use App\Models\DailyMatch;
use App\Models\EquipoPartido;
use Illuminate\Database\Eloquent\Model;

class Field extends Model
{
    protected $table = 'fields';
   

    protected $fillable = [
        'name', 'description', 'location', 'price_per_hour', 'duration_per_match', 
        'latitude', 'longitude', 'is_active', 'types', 'municipio', 'available_hours', 
        'amenities', 'images', 'price_per_match'
    ];
  
    protected $casts = [
        'available_hours' => 'array',
        'amenities' => 'array',
        'images' => 'array',
        'types' => 'array',  
        'is_active' => 'boolean',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'price_per_match' => 'decimal:4'
    ];

    public function bookings()
    {
        return $this->hasMany(Booking::class, 'field_id');
    }
   
    
    public function equipoPartidos()
    {
        return $this->hasMany(DailyMatch::class, 'field_id'); // Cambiar a DailyMatch
    }

    
}