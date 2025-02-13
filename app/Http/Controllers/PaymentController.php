<?php

namespace App\Http\Controllers;

use App\Services\MercadoPagoService;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    protected $mercadoPagoService;

    public function __construct(MercadoPagoService $mercadoPagoService)
    {
        $this->mercadoPagoService = $mercadoPagoService;
    }

  public function createPreference(Request $request)
{
    try {
        Log::info('Received request for preference:', $request->all());

        // Validar request
        $request->validate([
            'items' => 'required|array',
            'field_id' => 'required|exists:fields,id',
            'date' => 'required|date',
            'start_time' => 'required',
            'players_needed' => 'nullable|integer'
        ]);

        // Crear orden con la información de la reserva
        $order = Order::create([
            'user_id' => auth()->id(),
            'total' => collect($request->items)->sum(function($item) {
                return $item['quantity'] * $item['unit_price'];
            }),
            'status' => 'pending',
            // Guardar la información de la reserva
            'payment_details' => [
                'field_id' => $request->field_id,
                'date' => $request->date,
                'start_time' => $request->start_time,
                'players_needed' => $request->players_needed
            ]
        ]);

        // Crear preferencia en MercadoPago
        $preferenceData = [
            'items' => array_map(function($item) {
                return [
                    'title' => $item['title'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'currency_id' => 'MXN'
                ];
            }, $request->items),
            'external_reference' => (string)$order->id,
            'back_urls' => [
    'success' => 'https://proyect.aftconta.mx/payment/success',
    'failure' => 'https://proyect.aftconta.mx/payment/failure',
    'pending' => 'https://proyect.aftconta.mx/payment/pending',
],
            'auto_return' => 'approved',
            'notification_url' => env('MP_WEBHOOK_URL'),
            'binary_mode' => false  
        ];

        $preference = $this->mercadoPagoService->createPreference($preferenceData);

        // Actualizar orden con el ID de preferencia
        $order->update(['preference_id' => $preference['id']]);

        return response()->json([
            'init_point' => $preference['init_point'],
            'preference_id' => $preference['id']
        ]);

    } catch (\Exception $e) {
        Log::error('Payment Error:', ['error' => $e->getMessage()]);
        return response()->json(['error' => 'Error processing payment'], 500);
    }
}
    public function success(Request $request)
    {
        try {
            $paymentId = $request->payment_id;
            $status = $request->status;
            $externalReference = $request->external_reference;

            // Obtener información detallada del pago
            $paymentInfo = $this->mercadoPagoService->getPaymentInfo($paymentId);

            // Actualizar orden
            $order = Order::findOrFail($externalReference);
            $order->update([
                'status' => $status,
                'payment_id' => $paymentId,
                'payment_details' => $paymentInfo
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Payment processed successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Success Callback Error:', ['message' => $e->getMessage()]);
            return response()->json(['error' => 'Error processing callback'], 500);
        }
    }

    public function failure(Request $request)
    {
        // Implementar lógica para pagos fallidos
        return response()->json(['status' => 'failure']);
    }

    public function pending(Request $request)
    {
        // Implementar lógica para pagos pendientes
        return response()->json(['status' => 'pending']);
    }
}