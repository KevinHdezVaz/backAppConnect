<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Booking;
use Carbon\Carbon;
use App\Services\MercadoPagoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class WebhookController extends Controller
{
    protected $mercadoPagoService;

    public function __construct(MercadoPagoService $mercadoPagoService)
    {
        $this->mercadoPagoService = $mercadoPagoService;
    }

    public function handleMercadoPago(Request $request)
    {
        try {
            Log::info('=== MercadoPago Webhook Start ===');
            Log::info('Request Data:', $request->all());

            // Manejar diferentes tipos de notificaciones
            if ($request->type === 'payment') {
                return $this->handlePaymentNotification($request);
            } else if ($request->topic === 'merchant_order') {
                return $this->handleMerchantOrderNotification($request);
            } else {
                Log::info('Notification type not handled:', ['type' => $request->type, 'topic' => $request->topic]);
                return response()->json(['status' => 'ignored']);
            }
        } catch (\Exception $e) {
            Log::error('Webhook Error:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Error processing webhook'], 500);
        }
    }

    private function handlePaymentNotification(Request $request)
    {
        $paymentId = $request->data['id'];
        Log::info('Processing payment notification:', ['payment_id' => $paymentId]);

        $paymentInfo = $this->mercadoPagoService->getPaymentInfo($paymentId);
        return $this->processPayment($paymentInfo);
    }

    private function handleMerchantOrderNotification(Request $request)
    {
        try {
            $merchantOrderUrl = $request->resource;
            Log::info('Processing merchant order:', ['url' => $merchantOrderUrl]);

            // Obtener información de la orden desde MercadoPago
            $response = Http::withToken($this->mercadoPagoService->getAccessToken())
                ->get($merchantOrderUrl);

            if (!$response->successful()) {
                Log::error('Error getting merchant order:', $response->json());
                return response()->json(['error' => 'Error getting merchant order'], 400);
            }

            $orderData = $response->json();
            Log::info('Merchant order data:', $orderData);

            // Procesar los pagos asociados a la orden
            if (isset($orderData['payments']) && !empty($orderData['payments'])) {
                foreach ($orderData['payments'] as $payment) {
                    if ($payment['status'] === 'approved') {
                        $paymentInfo = $this->mercadoPagoService->getPaymentInfo($payment['id']);
                        $this->processPayment($paymentInfo);
                    }
                }
            }

            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            Log::error('Error processing merchant order:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Error processing merchant order'], 500);
        }
    }

    private function processPayment($paymentInfo)
    {
        Log::info('Processing payment:', $paymentInfo);

        // Verificar si preference_id está presente en la respuesta
        $preferenceId = $paymentInfo['preference_id'] ?? null;

        // Si no está presente, intentar obtenerlo desde external_reference o order
        if (!$preferenceId && isset($paymentInfo['order']['preference_id'])) {
            $preferenceId = $paymentInfo['order']['preference_id'];
        }

        if (!$preferenceId && isset($paymentInfo['external_reference'])) {
            $preferenceId = $paymentInfo['external_reference'];
        }

        if (!$preferenceId) {
            Log::error('Preference ID not found in payment info:', $paymentInfo);
            return response()->json(['error' => 'Preference ID not found'], 400);
        }

        // Buscar orden
        $order = Order::where('id', $preferenceId)
                     ->orWhere('preference_id', $preferenceId)
                     ->first();

        if (!$order) {
            Log::error('Order not found:', [
                'external_reference' => $preferenceId,
                'preference_id' => $preferenceId
            ]);
            return response()->json(['error' => 'Order not found'], 404);
        }

        if ($paymentInfo['status'] === 'approved') {
            Log::info('Payment approved, creating booking');

            // Actualizar orden
            $order->update([
                'status' => 'completed',
                'payment_id' => $paymentInfo['id']
            ]);

            // Crear reserva
            $bookingData = $order->payment_details['additional_info'];
            
            $booking = Booking::create([
                'user_id' => $order->user_id,
                'field_id' => $bookingData['field_id'],
                'start_time' => Carbon::parse($bookingData['date'] . ' ' . $bookingData['start_time']),
                'end_time' => Carbon::parse($bookingData['date'] . ' ' . $bookingData['start_time'])->addHour(),
                'total_price' => $order->total,
                'status' => 'confirmed',
                'payment_status' => 'completed',
                'payment_id' => $paymentInfo['id'],
                'players_needed' => $bookingData['players_needed'] ?? null
            ]);

            Log::info('Booking created successfully:', $booking->toArray());
        }

        return response()->json(['status' => 'success']);
    }
}