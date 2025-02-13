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
        try {
            Log::info('Processing payment:', $paymentInfo);

            // Validar que el external_reference esté presente
            if (empty($paymentInfo['external_reference'])) {
                Log::error('External reference not found in payment info:', $paymentInfo);
                return response()->json(['error' => 'External reference not found'], 400);
            }

            // Buscar orden por external_reference
            $order = Order::where('id', $paymentInfo['external_reference'])->first();

            if (!$order) {
                Log::error('Order not found:', [
                    'payment_info' => $paymentInfo,
                    'external_reference' => $paymentInfo['external_reference']
                ]);
                return response()->json(['error' => 'Order not found'], 404);
            }

            Log::info('Order found:', [
                'order_id' => $order->id,
                'payment_details' => $order->payment_details
            ]);

            if ($paymentInfo['status'] === 'approved') {
                Log::info('Payment approved, creating booking');

                // Actualizar orden con información completa del pago
                $order->update([
                    'status' => 'completed',
                    'payment_id' => $paymentInfo['id'],
                    'payment_details' => array_merge(
                        $order->payment_details ?? [],
                        ['payment_info' => $paymentInfo]
                    )
                ]);

                // Verificar si ya existe una reserva
                $existingBooking = Booking::where('payment_id', $paymentInfo['id'])->first();
                if ($existingBooking) {
                    Log::info('Booking already exists:', $existingBooking->toArray());
                    return response()->json([
                        'status' => 'success',
                        'booking_id' => $existingBooking->id,
                        'message' => 'Booking already exists'
                    ]);
                }

                // Crear reserva
                $booking = Booking::create([
                    'user_id' => $order->user_id,
                    'field_id' => $order->payment_details['field_id'],
                    'start_time' => Carbon::parse($order->payment_details['date'] . ' ' . $order->payment_details['start_time']),
                    'end_time' => Carbon::parse($order->payment_details['date'] . ' ' . $order->payment_details['start_time'])->addHour(),
                    'total_price' => $order->total,
                    'status' => 'confirmed',
                    'payment_status' => 'completed',
                    'payment_id' => $paymentInfo['id'],
                    'players_needed' => $order->payment_details['players_needed'] ?? null,
                    'allow_joining' => false
                ]);

                Log::info('Booking created successfully:', [
                    'booking_id' => $booking->id,
                    'user_id' => $booking->user_id,
                    'field_id' => $booking->field_id,
                    'start_time' => $booking->start_time,
                    'payment_id' => $booking->payment_id
                ]);

                return response()->json([
                    'status' => 'success',
                    'booking_id' => $booking->id,
                    'message' => 'New booking created'
                ]);
            }

            // Si el pago no está aprobado
            Log::info('Payment not approved:', [
                'status' => $paymentInfo['status'],
                'order_id' => $order->id
            ]);

            return response()->json([
                'status' => 'pending',
                'payment_status' => $paymentInfo['status']
            ]);

        } catch (\Exception $e) {
            Log::error('Error processing payment:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'payment_info' => $paymentInfo
            ]);
            throw $e;
        }
    }
}