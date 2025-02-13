<?php
 namespace App\Http\Controllers\API;

use Carbon\Carbon;
use App\Models\Field;
use App\Models\Booking;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Services\MercadoPagoService;

class BookingController extends Controller 
{

    protected $mercadoPagoService;

    public function __construct(MercadoPagoService $mercadoPagoService)
    {
        $this->mercadoPagoService = $mercadoPagoService;
    }

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
            'allow_joining' => 'boolean',
            'payment_id' => 'required|string', // Nuevo campo para MercadoPago
        ]);

        try {
            // Verificar el pago en MercadoPago
            $paymentInfo = $this->mercadoPagoService->getPaymentInfo($validated['payment_id']);
            
            if ($paymentInfo['status'] !== 'approved') {
                return response()->json([
                    'message' => 'El pago aún no ha sido aprobado'
                ], 422);
            }

            $field = Field::findOrFail($validated['field_id']);
            $startTime = Carbon::parse($validated['date'] . ' ' . $validated['start_time']);
            $endTime = $startTime->copy()->addMinutes(60);

            if (!$this->checkAvailability($field->id, $startTime, $endTime)) {
                return response()->json([
                    'message' => 'Horario no disponible'
                ], 422);
            }

            // Crear la reserva con la información del pago
            $booking = Booking::create([
                'user_id' => auth()->id(),
                'field_id' => $field->id,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'total_price' => $field->price_per_match,
                'status' => 'confirmed', // Cambiado a 'confirmed' ya que el pago está aprobado
                'players_needed' => $validated['players_needed'],
                'allow_joining' => $validated['allow_joining'] ?? false,
                'payment_id' => $validated['payment_id'],
                'payment_status' => 'completed'
            ]);

            return response()->json($booking->load('field'), 201);

        } catch (\Exception $e) {
            \Log::error('Error creating booking: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al crear la reserva: ' . $e->getMessage()
            ], 500);
        }
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
            ->where(function($query) {
                $query->where('status', 'confirmed')
                    ->orWhere('status', 'pending');
            })
            ->where('end_time', '>', now())
            ->orderBy('start_time')
            ->get();
    
        \Log::info('Active Reservations Query:', [
            'user_id' => auth()->id(),
            'bookings' => $bookings->toArray()
        ]);
    
        return response()->json($bookings);
    }
    


    public function getReservationHistory()
{
    $bookings = auth()->user()->bookings()
        ->with('field')
        ->where(function($query) {
            $query->where('status', 'completed')
                ->orWhere('status', 'cancelled')
                ->orWhere(function($q) {
                    $q->where('end_time', '<', now())
                      ->where('status', '!=', 'pending'); // No mostrar pendientes en el historial
                });
        })
        ->orderBy('start_time', 'desc')
        ->get();

    return response()->json($bookings);
}
}