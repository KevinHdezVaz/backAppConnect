<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use MercadoPago\SDK;
use MercadoPago\Preference;
use MercadoPago\Item;

class MercadoPagoController extends Controller
{
    public function __construct()
    {
        SDK::setAccessToken(config('services.mercadopago.access_token'));
    }

    public function createPreference(Request $request)
    {
        try {
            $preference = new Preference();

            // Crear los items para Mercado Pago
            $items = [];
            foreach ($request->items as $cartItem) {
                $item = new Item();
                $item->title = $cartItem['name'];
                $item->quantity = $cartItem['quantity'];
                $item->unit_price = $cartItem['price'];
                $items[] = $item;
            }

            $preference->items = $items;

            // URLs de retorno a tu aplicación
            $preference->back_urls = [
                "success" => "https://proyect.aftconta.mx/api/payments/success",
                "failure" => "https://proyect.aftconta.mx/api/payments/failure",
                "pending" => "https://proyect.aftconta.mx/api/payments/pending"
            ];

            // Redirigir automáticamente si el pago es aprobado
            $preference->auto_return = "approved";

            // Referencia externa para identificar la orden
            $preference->external_reference = $request->additionalData['external_reference'] ?? uniqid();

            // Datos del comprador si están disponibles
            if (isset($request->additionalData['customer'])) {
                $preference->payer = [
                    "name" => $request->additionalData['customer']['name'],
                    "email" => $request->additionalData['customer']['email'],
                ];
            }

            $preference->notification_url = "https://proyect.aftconta.mx/api/payments/webhook";

            $preference->save();

            return response()->json([
                'init_point' => $preference->init_point
            ]);

        } catch (\Exception $e) {
            \Log::error('Error MercadoPago: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function handleSuccess(Request $request)
    {
        \Log::info('Pago exitoso', $request->all());

        // Actualizar el estado del pedido en tu base de datos
        $payment_id = $request->payment_id;
        $status = $request->status;
        $external_reference = $request->external_reference;

        // Aquí puedes agregar la lógica para actualizar el pedido
        // Order::where('external_reference', $external_reference)->update(['status' => 'completed']);

        // Redireccionar a tu app móvil
        return redirect()->away('tuapp://checkout/success');
    }

    public function handleFailure(Request $request)
    {
        \Log::info('Pago fallido', $request->all());

        // Actualizar el estado del pedido en tu base de datos
        $external_reference = $request->external_reference;

        // Order::where('external_reference', $external_reference)->update(['status' => 'failed']);

        return redirect()->away('tuapp://checkout/failure');
    }

    public function handlePending(Request $request)
    {
        \Log::info('Pago pendiente', $request->all());

        return redirect()->away('tuapp://checkout/pending');
    }

    public function handleWebhook(Request $request)
    {
        \Log::info('Webhook recibido', $request->all());

        try {
            $data = $request->all();

            // Verificar el tipo de notificación
            if ($data['type'] === 'payment') {
                $payment_id = $data['data']['id'];

                // Obtener información del pago
                $payment = SDK::get("/v1/payments/$payment_id");

                // Actualizar el estado del pedido según la información recibida
                $external_reference = $payment->external_reference;
                $status = $payment->status;

                // Aquí tu lógica para actualizar el pedido
                switch ($status) {
                    case 'approved':
                        // Order::where('external_reference', $external_reference)
                        //     ->update(['status' => 'completed']);
                        break;
                    case 'rejected':
                        // Order::where('external_reference', $external_reference)
                        //     ->update(['status' => 'failed']);
                        break;
                    case 'pending':
                        // Order::where('external_reference', $external_reference)
                        //     ->update(['status' => 'pending']);
                        break;
                }
            }

            return response()->json(['status' => 'ok']);
        } catch (\Exception $e) {
            \Log::error('Error en webhook: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
