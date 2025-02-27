<?php
 namespace App\Http\Controllers\API;

use Carbon\Carbon;
use App\Models\Field;
use App\Models\Booking;
use Illuminate\Http\Request;
use App\Services\WalletService;
use App\Http\Controllers\Controller;
use App\Services\MercadoPagoService;

class BookingController extends Controller 
{

    protected $mercadoPagoService;
    protected $walletService;

    public function __construct(MercadoPagoService $mercadoPagoService, WalletService $walletService)    {
        $this->mercadoPagoService = $mercadoPagoService;
        $this->walletService = $walletService;  
    }

    public function index() 
    {
        $bookings = auth()->user()->bookings()
            ->with('field')
            ->orderBy('start_time', 'desc')
            ->get();
        return response()->json($bookings);
    }

   
    public function checkPaymentExists($paymentId)
{
    $booking = Booking::where('payment_id', $paymentId)->first();
    
    return response()->json([
        'exists' => $booking !== null,
        'booking_id' => $booking ? $booking->id : null,
        'message' => $booking ? 'Reserva encontrada con este ID de pago' : 'No se encontró ninguna reserva con este ID de pago'
    ]);
}
public function store(Request $request)
    {
        $validated = $request->validate([
            'field_id' => 'required|exists:fields,id',
            'date' => 'required|date|after_or_equal:today',
            'start_time' => 'required|date_format:H:i',
            'players_needed' => 'nullable|integer',
            'allow_joining' => 'boolean',
            'payment_id' => 'nullable|string',
            'order_id' => 'nullable|exists:orders,id',
            'use_wallet' => 'boolean',
        ]);

        try {
            // Verificar si ya existe una reserva con este payment_id (solo si se proporcionó)
            if (!empty($request->input('payment_id'))) {
                $existingBooking = Booking::where('payment_id', $request->input('payment_id'))->first();
                if ($existingBooking) {
                    return response()->json($existingBooking->load('field'), 200);
                }
            }

            $field = Field::findOrFail($validated['field_id']);
            $startTime = Carbon::parse($validated['date'] . ' ' . $validated['start_time']);
            $endTime = $startTime->copy()->addMinutes(60);

            if (!$this->checkAvailability($field->id, $startTime, $endTime)) {
                return response()->json(['message' => 'Horario no disponible'], 422);
            }

            $totalPrice = floatval($field->price_per_match);
            $amountToPay = $totalPrice;
            $paymentMethod = 'mercadopago';
            $paymentId = $request->input('payment_id'); // Usar input() para evitar undefined key

            // Si se usa el monedero
            if ($request->input('use_wallet', false)) {
                $wallet = auth()->user()->wallet;
                if ($wallet && $wallet->balance > 0) {
                    if ($wallet->balance >= $totalPrice) {
                        // Pago completo con monedero
                        $this->walletService->withdraw(auth()->user(), $totalPrice, "Pago de reserva para {$field->name}");
                        $amountToPay = 0;
                        $paymentMethod = 'wallet';
                        $paymentId = null; // No hay payment_id cuando se usa solo monedero
                    } else {
                        // Pago parcial con monedero
                        $amountToPay -= $wallet->balance;
                        $this->walletService->withdraw(auth()->user(), $wallet->balance, "Pago parcial con monedero para {$field->name}");
                        $paymentMethod = 'mixed';
                    }
                }
            }

            // Si queda algo por pagar, validar con MercadoPago
            if ($amountToPay > 0) {
                if (empty($request->input('payment_id')) || empty($request->input('order_id'))) {
                    return response()->json(['message' => 'Falta payment_id o order_id para pago con MercadoPago'], 422);
                }

                $order = Order::findOrFail($validated['order_id']);
                if ($order->type !== 'booking' || $order->reference_id != $validated['field_id']) {
                    return response()->json(['message' => 'Orden inválida para esta reserva'], 422);
                }

                if ($order->payment_id !== $request->input('payment_id') || $order->status !== 'completed') {
                    $paymentInfo = $this->mercadoPagoService->getPaymentInfo($request->input('payment_id'));
                    if ($paymentInfo['status'] !== 'approved') {
                        return response()->json(['message' => 'El pago aún no ha sido aprobado'], 422);
                    }
                    $order->update([
                        'payment_id' => $request->input('payment_id'),
                        'status' => 'completed',
                        'payment_details' => array_merge($order->payment_details, ['payment_info' => $paymentInfo]),
                    ]);
                }
            }

            $booking = Booking::create([
                'user_id' => auth()->id(),
                'field_id' => $field->id,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'total_price' => $totalPrice,
                'status' => 'confirmed',
                'players_needed' => $validated['players_needed'],
                'allow_joining' => $validated['allow_joining'] ?? false,
                'payment_id' => $paymentId,
                'payment_status' => 'completed',
                'payment_method' => $paymentMethod,
            ]);

            return response()->json([
                'success' => true,
                'data' => $booking->load('field'),
            ], 201);
        } catch (\Exception $e) {
            \Log::error('Error creating booking: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al crear la reserva',
                'error' => $e->getMessage(),
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

     return response()->json(array_values($availableHours));

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
   
public function cancel(Booking $booking, Request $request) 
    {
        if ($booking->user_id !== auth()->id()) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        if ($booking->status === 'cancelled') {
            return response()->json(['message' => 'La reserva ya está cancelada'], 400);
        }

        if ($booking->start_time < now()) {
            return response()->json(['message' => 'No se puede cancelar una reserva pasada'], 400);
        }

        // Actualizar la reserva
        $booking->update([
            'status' => 'cancelled',
            'cancellation_reason' => $request->input('reason'),
            'payment_status' => 'refunded', // Actualiza el estado del pago
        ]);

        // Reembolsar al monedero
        $this->walletService->refundBooking(
            auth()->user(),
            floatval($booking->total_price),
            "Reserva #{$booking->id}"
        );

        return response()->json([
            'message' => 'Reserva cancelada y reembolsada al monedero',
            'booking' => $booking,
            'refunded_amount' => $booking->total_price,
        ]);
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