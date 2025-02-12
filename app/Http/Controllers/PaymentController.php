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
            Log::info('Received request:', $request->all());  // Agregar este log
    
            // Validar request
            $request->validate([
                'items' => 'required|array',
                'items.*.title' => 'required|string',
                'items.*.quantity' => 'required|integer|min:1',
                'items.*.unit_price' => 'required|numeric|min:0',
            ]);
    
            Log::info('Validation passed');  // Agregar este log
    
            // Crear orden en tu base de datos
            $order = Order::create([
                'user_id' => auth()->id(),
                'total' => collect($request->items)->sum(function($item) {
                    return $item['quantity'] * $item['unit_price'];
                }),
                'status' => 'pending'
            ]);
    
            Log::info('Order created:', ['order_id' => $order->id]);  // Agregar este log
    

            // Preparar items para MercadoPago
            $items = collect($request->items)->map(function($item) {
                return [
                    'title' => $item['title'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'currency_id' => 'MXN' // Cambiar según el país
                ];
            })->toArray();

            // URLs de retorno
            $backUrls = [
                'success' => route('payments.success'),
                'failure' => route('payments.failure'),
                'pending' => route('payments.pending')
            ];

            // Crear preferencia en MercadoPago
            $preference = $this->mercadoPagoService->createPreference(
                $items,
                $backUrls,
                $order->id // external_reference
            );

            // Actualizar orden con preference_id
            $order->update([
                'preference_id' => $preference['id']
            ]);

            return response()->json([
                'init_point' => $preference['init_point'],
                'preference_id' => $preference['id']
            ]);

        } catch (\Exception $e) {
            Log::error('Payment Error:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()  // Agregar el stack trace
            ]);
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