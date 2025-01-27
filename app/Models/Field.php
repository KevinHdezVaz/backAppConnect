<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// app/Models/Field.php
class Field extends Model {
    protected $fillable = [
        'name', 'description', 'location', 'price_per_hour',
        'available_hours', 'amenities', 'images', 'price_per_match',
        'duration_per_match', 'latitude', 'longitude', 'is_active', 'type'
    ];

    protected $casts = [
        'available_hours' => 'array',
        'amenities' => 'array',
        'images' => 'array',
        'is_active' => 'boolean'
    ];

    public function bookings() {
        return $this->hasMany(Booking::class);
    }
}