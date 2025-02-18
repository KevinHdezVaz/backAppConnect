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

    public function test()
    {
        // Endpoint para probar que el webhook está accesible
        return response()->json(['status' => 'webhook endpoint is working']);
    }

    public function handleMercadoPago(Request $request)
    {
        try {
            Log::info('=== MercadoPago Webhook Start ===');
            Log::info('Request Data:', $request->all());
            Log::info('Request Headers:', $request->headers->all());

            // Validar que la petición viene de MercadoPago
            if (!$this->validateMercadoPagoRequest($request)) {
                Log::error('Invalid webhook signature');
                return response()->json(['error' => 'Invalid signature'], 401);
            }

            if ($request->type === 'payment') {
                return $this->handlePaymentNotification($request);
            } else if ($request->topic === 'merchant_order') {
                return $this->handleMerchantOrderNotification($request);
            } else {
                Log::info('Notification type not handled:', [
                    'type' => $request->type,
                    'topic' => $request->topic
                ]);
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

    private function validateMercadoPagoRequest(Request $request)
    {
        // Aquí puedes agregar validaciones adicionales si es necesario
        return true;
    }

    private function handlePaymentNotification(Request $request)
    {
        try {
            $paymentId = $request->data['id'];
            Log::info('Processing payment notification:', ['payment_id' => $paymentId]);

            $paymentInfo = $this->mercadoPagoService->getPaymentInfo($paymentId);

            Log::info('Payment info received:', $paymentInfo);

            return $this->processPayment($paymentInfo);
        } catch (\Exception $e) {
            Log::error('Error in payment notification:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    private function handleMerchantOrderNotification(Request $request)
    {
        try {
            $merchantOrderUrl = $request->resource;
            Log::info('Processing merchant order:', ['url' => $merchantOrderUrl]);

            $response = Http::withToken($this->mercadoPagoService->getAccessToken())
                ->get($merchantOrderUrl);

            if (!$response->successful()) {
                Log::error('Error getting merchant order:', $response->json());
                return response()->json(['error' => 'Error getting merchant order'], 400);
            }

            $orderData = $response->json();
            Log::info('Merchant order data:', $orderData);

            $results = [];
            if (isset($orderData['payments']) && !empty($orderData['payments'])) {
                foreach ($orderData['payments'] as $payment) {
                    if ($payment['status'] === 'approved') {
                        $paymentInfo = $this->mercadoPagoService->getPaymentInfo($payment['id']);
                        $results[] = $this->processPayment($paymentInfo);
                    }
                }
            }

            return response()->json([
                'status' => 'success',
                'results' => $results
            ]);
        } catch (\Exception $e) {
            Log::error('Error processing merchant order:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    private function processPayment($paymentInfo)
    {
        try {
            Log::info('Processing payment:', $paymentInfo);
    
            if (empty($paymentInfo['external_reference'])) {
                throw new \Exception('External reference not found in payment info');
            }
    
            $order = Order::where('id', $paymentInfo['external_reference'])->first();
    
            if (!$order) {
                throw new \Exception('Order not found: ' . $paymentInfo['external_reference']);
            }
    
            Log::info('Order found:', [
                'order_id' => $order->id,
                'payment_details' => $order->payment_details
            ]);
    
            // Manejar todos los estados posibles
            switch ($paymentInfo['status']) {
                case 'approved':
                    return $this->handleApprovedPayment($order, $paymentInfo);
                case 'rejected':
                case 'cancelled':
                    $order->update(['status' => 'failed']);
                    break;
                case 'pending':
                case 'in_process':
                    $order->update(['status' => 'pending']);
                    break;
                case 'authorized':
                    $order->update(['status' => 'authorized']);
                    break;
                default:
                    Log::warning('Estado de pago desconocido:', ['status' => $paymentInfo['status']]);
                    $order->update(['status' => 'unknown']);
                    break;
            }
    
            return response()->json([
                'status' => 'updated',
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
    private function handleApprovedPayment($order, $paymentInfo)
    {
        // Actualizar orden
        $order->update([
            'status' => 'completed',
            'payment_id' => $paymentInfo['id'],
            'payment_details' => array_merge(
                $order->payment_details ?? [],
                ['payment_info' => $paymentInfo]
            )
        ]);

        // Verificar reserva existente
        $existingBooking = Booking::where('payment_id', $paymentInfo['id'])->first();
        if ($existingBooking) {
            return response()->json([
                'status' => 'success',
                'booking_id' => $existingBooking->id,
                'message' => 'Booking already exists'
            ]);
        }

        // Crear nueva reserva
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
            'field_id' => $booking->field_id,
            'start_time' => $booking->start_time
        ]);

        return response()->json([
            'status' => 'success',
            'booking_id' => $booking->id,
            'message' => 'New booking created'
        ]);
    }
}