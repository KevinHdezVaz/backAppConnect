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
            $paymentInfo = $this->mercadoPagoService->validatePaymentStatus($paymentId);
            return response()->json([
                'status' => $paymentInfo['status'],
                'is_approved' => $paymentInfo['is_approved'],
            ]);
        } catch (\Exception $e) {
            Log::error('Error al verificar el estado del pago: ' . $e->getMessage());
            return response()->json(['error' => 'Error al verificar el estado del pago'], 500);
        }
    }
    
    public function createPreference(Request $request)
    {
        try {
            $request->validate([
                'items' => 'required|array',
                'items.*.title' => 'required|string',
                'items.*.quantity' => 'required|integer',
                'items.*.unit_price' => 'required|numeric',
                'type' => 'required|in:booking,bono', // Tipos permitidos
                'reference_id' => 'required|integer', // ID del recurso (field_id o bono_id)
            ]);

            $order = Order::create([
                'user_id' => auth()->id(),
                'total' => collect($request->items)->sum(fn($item) => $item['quantity'] * $item['unit_price']),
                'status' => 'pending',
                'type' => $request->type,
                'reference_id' => $request->reference_id,
                'payment_details' => $request->additionalData ?? [], // Solo datos adicionales
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
                'payer' => [
                    'name' => auth()->user()->name,
                    'email' => auth()->user()->email,
                ],
            ];

            $preference = $this->mercadoPagoService->createPreference($preferenceData);

            $order->update(['preference_id' => $preference['id']]);

            return response()->json([
                'init_point' => $preference['init_point'],
                'order_id' => $order->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Error al crear preferencia', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Error al procesar el pago'], 500);
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