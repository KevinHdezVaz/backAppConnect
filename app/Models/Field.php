<?php

namespace App\Models;

use App\Models\Booking;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

// app/Models/Field.php
class Field extends Model {
    protected $fillable = [
        'name', 'description', 'location',
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