<?php
// app/Http/Controllers/API/BookingController.php
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
            'date' => 'required|date|after:today',
            'start_time' => 'required|date_format:H:i',
            'players_needed' => 'nullable|integer',
            'allow_joining' => 'boolean'
        ]);

        $field = Field::findOrFail($validated['field_id']);
        $startTime = Carbon::parse($validated['date'] . ' ' . $validated['start_time']);
        $endTime = $startTime->copy()->addMinutes($field->duration_per_match ?? 60);

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