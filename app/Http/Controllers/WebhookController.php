<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\MercadoPagoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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
            Log::info('MercadoPago Webhook:', $request->all());

            if ($request->type === 'payment') {
                $paymentInfo = $this->mercadoPagoService->getPaymentInfo($request->data['id']);
                
                $order = Order::where('preference_id', $paymentInfo['preference_id'])->first();
                
                if ($order) {
                    $order->update([
                        'status' => $paymentInfo['status'],
                        'payment_id' => $paymentInfo['id'],
                        'payment_details' => $paymentInfo
                    ]);
                }
            }

            return response()->json(['status' => 'success']);

        } catch (\Exception $e) {
            Log::error('Webhook Error:', ['message' => $e->getMessage()]);
            return response()->json(['error' => 'Error processing webhook'], 500);
        }
    }
}

