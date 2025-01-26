<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    protected $fillable = ['user_id', 'field_id', 'start_time', 'end_time', 'total_price', 'status'];
    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime'
    ];
    public function user() {
        return $this->belongsTo(User::class);
    }
    public function field() {
        return $this->belongsTo(Field::class);
    }
}