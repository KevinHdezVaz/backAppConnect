<?php
namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MercadoPagoService
{
    protected $accessToken;
    protected $baseUrl = 'https://api.mercadopago.com';

    public function __construct()
    {
        $this->accessToken = config('services.mercadopago.access_token');
    }

    public function createPreference(array $items, array $backUrls, $externalReference = null)
    {
        try {
            Log::info('Creating MercadoPago preference:', [
                'items' => $items,
                'backUrls' => $backUrls,
                'externalReference' => $externalReference
            ]);

            $response = Http::withToken($this->accessToken)
                ->post($this->baseUrl . '/checkout/preferences', [
                    'items' => $items,
                    'back_urls' => $backUrls,
                    'external_reference' => $externalReference,
                    'auto_return' => 'approved',
                    'notification_url' => route('webhooks.mercadopago'),
                ]);

            Log::info('MercadoPago response:', [
                'status' => $response->status(),
                'body' => $response->json()
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('MercadoPago Error:', $response->json());
            throw new \Exception('Error creating preference in MercadoPago: ' . json_encode($response->json()));
        } catch (\Exception $e) {
            Log::error('MercadoPago Exception:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    public function getPaymentInfo($paymentId)
    {
        try {
            $response = Http::withToken($this->accessToken)
                ->get($this->baseUrl . "/v1/payments/{$paymentId}");

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('MercadoPago Payment Error:', $response->json());
            throw new \Exception('Error getting payment info from MercadoPago');
        } catch (\Exception $e) {
            Log::error('MercadoPago Exception:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
}