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

   
    // BookingController.php
public function store(Request $request)
{
    $validated = $request->validate([
        'field_id' => 'required|exists:fields,id',
        'date' => 'required|date|after:today',
        'start_time' => 'required|date_format:H:i',
        'duration' => 'required|integer|min:1|max:3', // horas
        'players_needed' => 'nullable|integer',
        'allow_joining' => 'boolean'
    ]);

    // Verificar disponibilidad
    $isAvailable = $this->checkAvailability(
        $validated['field_id'],
        $validated['date'],
        $validated['start_time'],
        $validated['duration']
    );

    if (!$isAvailable) {
        return response()->json([
            'message' => 'Horario no disponible'
        ], 422);
    }

    // Calcular precio
    $field = Field::findOrFail($validated['field_id']);
    $totalPrice = $field->price_per_hour * $validated['duration'];

    // Crear reserva
    $booking = Booking::create([
        'user_id' => auth()->id(),
        'field_id' => $validated['field_id'],
        'start_time' => $validated['date'] . ' ' . $validated['start_time'],
        'end_time' => Carbon::parse($validated['date'] . ' ' . $validated['start_time'])
            ->addHours($validated['duration']),
        'total_price' => $totalPrice,
        'status' => 'pending',
        'players_needed' => $validated['players_needed'],
        'allow_joining' => $validated['allow_joining'] ?? false
    ]);

    return response()->json($booking->load('field'), 201);
}

private function checkAvailability($fieldId, $date, $startTime, $duration)
{
    $start = Carbon::parse($date . ' ' . $startTime);
    $end = $start->copy()->addHours($duration);

    return !Booking::where('field_id', $fieldId)
        ->where('status', '!=', 'cancelled')
        ->where(function ($query) use ($start, $end) {
            $query->whereBetween('start_time', [$start, $end])
                ->orWhereBetween('end_time', [$start, $end]);
        })->exists();
}

}