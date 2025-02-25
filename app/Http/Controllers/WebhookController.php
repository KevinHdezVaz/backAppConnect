<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Booking;
use App\Models\UserBono;
use Carbon\Carbon;
use App\Services\MercadoPagoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class WebhookController extends Controller
{
    protected $mercadoPagoService;

    public function __construct(MercadoPagoService $mercadoPagoService)
    {
        $this->mercadoPagoService = $mercadoPagoService;
    }

    public function test()
    {
        return response()->json(['status' => 'webhook endpoint is working']);
    }

    public function handleMercadoPago(Request $request)
    {
        try {
            Log::info('=== MercadoPago Webhook Start ===');
            Log::info('Request Data:', $request->all());
            Log::info('Request Headers:', $request->headers->all());
    
            if (!$this->validateMercadoPagoRequest($request)) {
                Log::error('Invalid webhook signature');
                return response()->json(['error' => 'Invalid signature'], 401);
            }
    
            $data = $request->all();
    
            // Verificar si ya procesamos esta notificación (por id)
            if (isset($data['id'])) {
                 $existingLog = false; // Por ahora simplemente deshabilitamos la verificación de duplicados

                if ($existingLog) {
                    Log::info('Notificación duplicada ignorada:', ['id' => $data['id']]);
                    return response()->json(['status' => 'duplicated'], 200);
                }
            }
    
            if ($request->type === 'payment') {
                return $this->handlePaymentNotification($request);
            } elseif ($request->topic === 'merchant_order') {
                return $this->handleMerchantOrderNotification($request);
            } elseif ($request->topic === 'payment') {
                return $this->handlePaymentNotificationById($request->id);
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
    
    private function processPayment($paymentInfo)
    {
        try {
            Log::info('Processing payment:', $paymentInfo);
    
            if (empty($paymentInfo['external_reference'])) {
                throw new \Exception('External reference not found in payment info');
            }
    
            $orderId = $paymentInfo['external_reference'];
            $order = Order::where('id', $orderId)->first();
    
            if (!$order) {
                throw new \Exception('Order not found: ' . $orderId);
            }
    
            Log::info('Order found:', [
                'order_id' => $order->id,
                'type' => $order->type,
                'reference_id' => $order->reference_id,
                'payment_details' => $order->payment_details
            ]);
    
            // Verificar si la orden ya está completada
            if ($order->status === 'completed') {
                Log::info('Order already completed:', ['order_id' => $order->id]);
                return response()->json([
                    'status' => 'already_completed',
                    'message' => 'Order already processed'
                ], 200);
            }
    
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
                    Log::warning('Unknown payment status:', ['status' => $paymentInfo['status']]);
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

    private function validateMercadoPagoRequest(Request $request)
    {
        return true; // Implementar validación de firma si es necesario
    }

    private function handlePaymentNotification(Request $request)
    {
        try {
            $paymentId = $request->data['id'];
            Log::info('Processing payment notification:', ['payment_id' => $paymentId]);
            return $this->processPayment($this->mercadoPagoService->getPaymentInfo($paymentId));
        } catch (\Exception $e) {
            Log::error('Error in payment notification:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    private function handlePaymentNotificationById($paymentId)
    {
        try {
            Log::info('Processing payment notification (topic: payment):', ['payment_id' => $paymentId]);
            return $this->processPayment($this->mercadoPagoService->getPaymentInfo($paymentId));
        } catch (\Exception $e) {
            Log::error('Error in payment notification (topic: payment):', [
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
 

    private function handleApprovedPayment(Order $order, $paymentInfo)
    {
        try {
            if ($order->status === 'completed') {
                Log::info('Order already completed:', ['order_id' => $order->id]);
                return response()->json([
                    'status' => 'already_completed',
                    'message' => 'Order already processed'
                ]);
            }

            $order->update([
                'status' => 'completed',
                'payment_id' => $paymentInfo['id'],
                'payment_details' => array_merge(
                    $order->payment_details ?? [],
                    ['payment_info' => $paymentInfo]
                )
            ]);

            switch ($order->type) {
                case 'booking':
                    return $this->processBooking($order, $paymentInfo);
                case 'bono':
                    return $this->processBono($order, $paymentInfo);
                default:
                    Log::warning('Unhandled order type:', ['type' => $order->type]);
                    return response()->json(['error' => 'Unknown order type'], 400);
            }
        } catch (\Exception $e) {
            Log::error('Error in handleApprovedPayment:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'order_id' => $order->id
            ]);
            throw $e;
        }
    }

    private function processBooking(Order $order, $paymentInfo)
    {
        $details = $order->payment_details;

        $existingBooking = Booking::where('payment_id', $paymentInfo['id'])->first();
        if ($existingBooking) {
            Log::info('Booking already exists:', ['booking_id' => $existingBooking->id]);
            return response()->json([
                'status' => 'success',
                'booking_id' => $existingBooking->id,
                'message' => 'Booking already exists'
            ]);
        }

        $startTime = Carbon::parse("{$details['date']} {$details['start_time']}");
        $endTime = $startTime->copy()->addHour();

        $booking = Booking::create([
            'user_id' => $order->user_id,
            'field_id' => $order->reference_id,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'total_price' => $order->total,
            'status' => 'confirmed',
            'payment_status' => 'completed',
            'payment_id' => $paymentInfo['id'],
            'players_needed' => $details['players_needed'] ?? null,
            'allow_joining' => $details['allow_joining'] ?? false
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
    private function processBono(Order $order, $paymentInfo)
    {
        // Primero verificar si ya existe un bono con este pago
        $existingUserBono = UserBono::where('payment_id', $paymentInfo['id'])->first();
        if ($existingUserBono) {
            Log::info('UserBono already exists with payment_id:', ['payment_id' => $paymentInfo['id']]);
            return response()->json([
                'status' => 'success',
                'user_bono_id' => $existingUserBono->id,
                'message' => 'UserBono already exists'
            ]);
        }
    
        // Segundo, verificar si ya existe un bono activo del mismo tipo para este usuario
        $existingBonoByType = UserBono::where('user_id', $order->user_id)
            ->where('bono_id', $order->reference_id)
            ->where('estado', 'activo')
            ->where('fecha_vencimiento', '>', now())
            ->first();
            
        if ($existingBonoByType) {
            Log::info('Active UserBono already exists for this user and bono_id:', [
                'user_id' => $order->user_id,
                'bono_id' => $order->reference_id
            ]);
            return response()->json([
                'status' => 'success',
                'user_bono_id' => $existingBonoByType->id,
                'message' => 'Active UserBono already exists'
            ]);
        }
    
        // Crear nuevo UserBono
        $bono = \App\Models\Bono::findOrFail($order->reference_id);
        $fechaCompra = now();
        $fechaVencimiento = $fechaCompra->copy()->addDays($bono->duracion_dias);
    
        $codigoReferencia = strtoupper(Str::random(8));
        while (UserBono::where('codigo_referencia', $codigoReferencia)->exists()) {
            $codigoReferencia = strtoupper(Str::random(8));
        }
    
        $userBono = UserBono::create([
            'user_id' => $order->user_id,
            'bono_id' => $order->reference_id,
            'fecha_compra' => $fechaCompra,
            'fecha_vencimiento' => $fechaVencimiento,
            'codigo_referencia' => $codigoReferencia,
            'payment_id' => $paymentInfo['id'],
            'estado' => 'activo',
            'usos_disponibles' => $bono->usos_totales ?? null,
            'usos_totales' => $bono->usos_totales ?? null,
        ]);
    
        Log::info('UserBono created successfully:', [
            'user_bono_id' => $userBono->id,
            'bono_id' => $userBono->bono_id,
            'codigo_referencia' => $userBono->codigo_referencia
        ]);
    
        return response()->json([
            'status' => 'success',
            'user_bono_id' => $userBono->id,
            'message' => 'New UserBono created'
        ]);
    }
}