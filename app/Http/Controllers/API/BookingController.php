<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Booking;

class BookingController extends Controller
{
    public function store(Request $request) {
        $validated = $request->validate([
            'field_id' => 'required|exists:fields,id',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time',
        ]);

        $booking = Booking::create([
            'user_id' => auth()->id(),
            'field_id' => $validated['field_id'],
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'total_price' => $this->calculatePrice($validated['field_id'], $validated['start_time'], $validated['end_time']),
            'status' => 'pending'
        ]);

        return response()->json($booking, 201);
    }

    private function calculatePrice($fieldId, $startTime, $endTime) {
        $field = Field::findOrFail($fieldId);
        $hours = (strtotime($endTime) - strtotime($startTime)) / 3600;
        return $field->price_per_hour * $hours;
    }
}