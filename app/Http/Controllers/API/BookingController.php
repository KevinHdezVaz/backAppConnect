<?php
 namespace App\Http\Controllers\API;

use Carbon\Carbon;
use App\Models\Field;
use App\Models\Booking;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class BookingController extends Controller 
{
    public function index() 
    {
        $bookings = auth()->user()->bookings()
            ->with('field')
            ->orderBy('start_time', 'desc')
            ->get();
        return response()->json($bookings);
    }

   
public function store(Request $request) 
{
    $validated = $request->validate([
        'field_id' => 'required|exists:fields,id',
        'date' => 'required|date|after_or_equal:today',  
        'start_time' => 'required|date_format:H:i',
        'players_needed' => 'nullable|integer',
        'allow_joining' => 'boolean'
    ]);

    $field = Field::findOrFail($validated['field_id']);
    $startTime = Carbon::parse($validated['date'] . ' ' . $validated['start_time']);
    $endTime = $startTime->copy()->addMinutes(60);  

    if (!$this->checkAvailability($field->id, $startTime, $endTime)) {
        return response()->json(['message' => 'Horario no disponible'], 422);
    }

    $booking = Booking::create([
        'user_id' => auth()->id(),
        'field_id' => $field->id,
        'start_time' => $startTime,
        'end_time' => $endTime,
        'total_price' => $field->price_per_match,
        'status' => 'pending',
        'players_needed' => $validated['players_needed'],
        'allow_joining' => $validated['allow_joining'] ?? false
    ]);

    return response()->json($booking->load('field'), 201);
}

public function getAvailableHours(Field $field, Request $request)
{
    // Validar la fecha
    $request->validate([
        'date' => 'required|date_format:Y-m-d',
    ]);

    $date = Carbon::parse($request->date);
    $dayOfWeek = strtolower($date->format('l'));  // Obtiene el día de la semana en minúsculas

    \Log::info('Requesting available hours', [
        'field_id' => $field->id,
        'date' => $request->date,
        'day_of_week' => $dayOfWeek
    ]);

    // Verificar si la fecha es pasada (excepto hoy)
    if ($date->isPast() && !$date->isToday()) {
        return response()->json([]); // Devuelve un array vacío
    }

    $availableHours = [];
    $storedHours = $field->available_hours;

    // Verificar si hay horarios almacenados para el día solicitado
    if (isset($storedHours[$dayOfWeek])) {
        foreach ($storedHours[$dayOfWeek] as $hour) {
            $startTime = Carbon::parse($date->format('Y-m-d') . ' ' . $hour);
            $endTime = $startTime->copy()->addMinutes(60);

            // Filtrar horas pasadas para el día actual
            if ($date->isToday() && $startTime->isPast()) {
                continue;
            }

            // Verificar si el horario está disponible
            if ($this->checkAvailability($field->id, $startTime, $endTime)) {
                $availableHours[] = $hour;
            }
        }
    }

    \Log::info('Filtered available hours', [
        'available_hours' => $availableHours,
    ]);

    return response()->json($availableHours); // Devuelve solo el array de horas
}


private function checkAvailability($fieldId, $startTime, $endTime) 
{
    return !Booking::where('field_id', $fieldId)
        ->where('status', '!=', 'cancelled')
        ->where(function($query) use ($startTime, $endTime) {
            $query->whereBetween('start_time', [$startTime, $endTime])
                ->orWhereBetween('end_time', [$startTime, $endTime]);
        })->exists();
}
    public function cancel(Booking $booking) 
    {
        if ($booking->user_id !== auth()->id()) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $booking->update([
            'status' => 'cancelled',
            'cancellation_reason' => request('reason')
        ]);

        return response()->json($booking);
    }

    public function getActiveReservations()
    {
        $bookings = auth()->user()->bookings()
            ->with('field')
            ->where('status', '!=', 'cancelled')
            ->where('end_time', '>', now())
            ->orderBy('start_time')
            ->get();

        return response()->json($bookings);
    }

    public function getReservationHistory()
    {
        $bookings = auth()->user()->bookings()
            ->with('field')
            ->where(function($query) {
                $query->where('status', 'completed')
                    ->orWhere('status', 'cancelled')
                    ->orWhere('end_time', '<', now());
            })
            ->orderBy('start_time', 'desc')
            ->get();

        return response()->json($bookings);
    }
}