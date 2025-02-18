<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\MercadoPagoService;

class PaymentController extends Controller
{
    protected $mercadoPagoService;

    public function __construct(MercadoPagoService $mercadoPagoService)
    {
        $this->mercadoPagoService = $mercadoPagoService;
    }

    public function verifyPaymentStatus($paymentId)
    {
        try {
            // Obtener información del pago desde MercadoPago usando el servicio
            $paymentInfo = $this->mercadoPagoService->validatePaymentStatus($paymentId);

            // Devolver el estado del pago
            return response()->json([
                'status' => $paymentInfo['status'],
                'is_approved' => $paymentInfo['is_approved'],
            ]);
        } catch (\Exception $e) {
            Log::error('Error al verificar el estado del pago: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al verificar el estado del pago',
            ], 500);
        }
    }

    public function createPreference(Request $request)
    {
        try {
            Log::info('Creando preferencia de pago', $request->all());

            // Validar la request
            $request->validate([
                'items' => 'required|array',
                'items.*.title' => 'required|string',
                'items.*.quantity' => 'required|integer',
                'items.*.unit_price' => 'required|numeric',
            ]);

            // Crear orden en la base de datos
            $order = Order::create([
                'user_id' => auth()->id(),
                'total' => collect($request->items)->sum(function ($item) {
                    return $item['quantity'] * $item['unit_price'];
                }),
                'status' => 'pending',
                'payment_details' => [
                    'field_id' => $request->additionalData['field_id'] ?? null,
                    'date' => $request->additionalData['date'] ?? null,
                    'start_time' => $request->additionalData['start_time'] ?? null,
                    'players_needed' => $request->additionalData['players_needed'] ?? null,
                ],
            ]);

            $preferenceData = [
                'items' => $request->items,
                'back_urls' => [
                    'success' => 'footconnect://checkout/success',
                    'failure' => 'footconnect://checkout/failure',
                    'pending' => 'footconnect://checkout/pending',
                ],
                'auto_return' => 'approved',
                'external_reference' => (string) $order->id,
                'notification_url' => 'https://proyect.aftconta.mx/api/webhook/mercadopago',
            ];

            if (isset($request->payer)) {
                $preferenceData['payer'] = [
                    'name' => $request->payer['name'],
                    'email' => $request->payer['email'],
                ];
            }

            // Crear preferencia en MercadoPago
            $preference = $this->mercadoPagoService->createPreference($preferenceData);

            // Actualizar orden con información de la preferencia
            $order->update([
                'payment_details' => array_merge(
                    $order->payment_details,
                    ['preference_id' => $preference['id'] ?? null]
                ),
            ]);

            return response()->json([
                'init_point' => $preference['init_point'],
                'order_id' => $order->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Error al crear preferencia', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Error al procesar el pago: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function handleSuccess(Request $request)
    {
        Log::info('Pago exitoso', $request->all());
        try {
            if ($request->payment_id) {
                $paymentInfo = $this->mercadoPagoService->getPaymentInfo($request->payment_id);
                $order = Order::findOrFail($paymentInfo['external_reference']);
                $order->update([
                    'status' => 'completed',
                    'payment_id' => $request->payment_id,
                    'payment_details' => array_merge(
                        $order->payment_details,
                        ['payment_info' => $paymentInfo]
                    ),
                ]);
            }
            return redirect('footconnect://checkout/success');
        } catch (\Exception $e) {
            Log::error('Error en success callback', [
                'error' => $e->getMessage(),
                'request' => $request->all(),
            ]);
            return redirect('footconnect://checkout/failure');
        }
    }

    public function handleFailure(Request $request)
    {
        Log::info('Pago fallido', $request->all());
        try {
            if ($request->payment_id) {
                $paymentInfo = $this->mercadoPagoService->getPaymentInfo($request->payment_id);
                $order = Order::findOrFail($paymentInfo['external_reference']);
                $order->update([
                    'status' => 'failed',
                    'payment_id' => $request->payment_id,
                    'payment_details' => array_merge(
                        $order->payment_details,
                        ['payment_info' => $paymentInfo]
                    ),
                ]);
            }
            return redirect('footconnect://checkout/failure');
        } catch (\Exception $e) {
            Log::error('Error en failure callback', [
                'error' => $e->getMessage(),
                'request' => $request->all(),
            ]);
            return redirect('footconnect://checkout/failure');
        }
    }

    public function handlePending(Request $request)
    {
        Log::info('Pago pendiente', $request->all());
        try {
            if ($request->payment_id) {
                $paymentInfo = $this->mercadoPagoService->getPaymentInfo($request->payment_id);
                $order = Order::findOrFail($paymentInfo['external_reference']);
                $order->update([
                    'status' => 'pending',
                    'payment_id' => $request->payment_id,
                    'payment_details' => array_merge(
                        $order->payment_details,
                        ['payment_info' => $paymentInfo]
                    ),
                ]);
            }
            return redirect('footconnect://checkout/pending');
        } catch (\Exception $e) {
            Log::error('Error en pending callback', [
                'error' => $e->getMessage(),
                'request' => $request->all(),
            ]);
            return redirect('footconnect://checkout/failure');
        }
    }
}