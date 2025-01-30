<?php

namespace App\Models;

use App\Models\User;
use App\Models\Field;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Booking extends Model {
    protected $fillable = [
        'user_id', 'field_id', 'start_time', 'end_time', 'total_price',
        'status', 'payment_status', 'payment_method', 'is_recurring',
        'cancellation_reason', 'allow_joining', 'players_needed', 'player_list'
    ];
 
    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'is_recurring' => 'boolean',
        'allow_joining' => 'boolean',
        'player_list' => 'array'
    ];
 
    public function user() {
        return $this->belongsTo(User::class);
    }
 
    public function field() {
        return $this->belongsTo(Field::class);
    }
 }