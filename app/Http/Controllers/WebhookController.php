<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Order;
use App\Models\Booking;
use App\Models\MatchTeam;
use App\Models\DailyMatch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\MercadoPagoService;
use Illuminate\Support\Facades\Http;
use App\Models\MatchPlayer;  // Añadir este import al inicio

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

            
            if ($paymentInfo['status'] === 'approved') {
                return $this->handleApprovedPayment($order, $paymentInfo);
            }

            // Manejar otros estados
            $order->update([
                'status' => $paymentInfo['status'],
                'payment_id' => $paymentInfo['id'],
                'payment_details' => array_merge(
                    $order->payment_details ?? [],
                    ['payment_info' => $paymentInfo]
                )
            ]);

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


    private function checkMatchStatus($match)
{
    // Contar jugadores con pago completado
    $paidPlayersCount = MatchPlayer::where('match_id', $match->id)
        ->where('payment_status', 'completed')
        ->count();

    $allTeamsFull = MatchTeam::where('equipo_partido_id', $match->id)
        ->get()
        ->every(function($team) {
            return $team->player_count >= $team->max_players;
        });

    if ($paidPlayersCount >= ($match->max_players * 7) && $allTeamsFull) {
        $match->update(['status' => 'confirmed']);
        
        // Aquí podrías agregar notificaciones a los jugadores
        $this->notifyMatchConfirmed($match);
    }


}
   
private function handleApprovedPayment($order, $paymentInfo) {
    try { 
        DB::beginTransaction();

        $paymentDetails = $order->payment_details;
        
        // Validar que los datos necesarios existen
        if (!isset($paymentDetails['match_id'], $paymentDetails['team_id'], $paymentDetails['position'])) {
            throw new \Exception('Datos de pago incompletos');
        }

        // Validar que el equipo existe
        $team = MatchTeam::find($paymentDetails['team_id']);
        if (!$team) {
            throw new \Exception('Equipo no encontrado');
        }

        // Crear el registro del jugador con los datos validados
        MatchPlayer::create([
            'match_id' => intval($paymentDetails['match_id']),
            'player_id' => $order->user_id,
            'equipo_partido_id' => intval($paymentDetails['team_id']),
            'position' => $paymentDetails['position'],
            'payment_status' => 'completed',
            'payment_id' => $paymentInfo['id'],
            'amount' => $paymentInfo['transaction_amount']
        ]);

        // Actualizar el contador del equipo
        $team->increment('player_count');

        DB::commit();
        return response()->json(['status' => 'success']);
        
    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Error processing team join payment: ' . $e->getMessage());
        throw $e;
    }
}
}
