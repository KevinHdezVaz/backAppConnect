<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Field;

class FieldController extends Controller
{
  
    
    public function index()
    {
        return response()->json(Field::all());
    }
    public function show(Field $field) {
        return $field;
    }

    public function checkAvailability(Field $field, Request $request) {
        $request->validate([
            'date' => 'required|date',
        ]);
        
        $bookings = $field->bookings()
            ->whereDate('start_time', $request->date)
            ->get();
            
        return response()->json([
            'available_hours' => $field->available_hours,
            'booked_hours' => $bookings
        ]);
    }
}