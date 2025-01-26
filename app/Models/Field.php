<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Field extends Model
{
    protected $fillable = ['name', 'description', 'location', 'price_per_hour', 'available_hours', 'amenities', 'images'];
    protected $casts = [
        'available_hours' => 'array',
        'amenities' => 'array'
    ];
    public function bookings() {
        return $this->hasMany(Booking::class);
    }
}
