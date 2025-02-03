<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Field extends Model
{
    protected $fillable = [
        'name',
        'description',
        'latitude',
        'longitude',
        'is_active',
        'municipio',
        'type',
        'available_hours',
        'amenities',
        'images',
        'price_per_match',
    ];

    protected $casts = [
        'available_hours' => 'array',
        'amenities' => 'array',
        'images' => 'array',
        'is_active' => 'boolean',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'price_per_match' => 'decimal:4'
    ];
}