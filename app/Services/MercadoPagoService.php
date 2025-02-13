<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MercadoPagoService
{
    protected $accessToken;
    protected $baseUrl = 'https://api.mercadopago.com';

    public function getAccessToken()
    {
        return $this->accessToken;
    }
    
    public function __construct()
    {
        $this->accessToken = config('services.mercadopago.access_token');
        
        if (empty($this->accessToken)) {
            Log::error('MercadoPago access token is not configured');
            throw new \Exception('MercadoPago access token is not configured');
        }
    }

    public function createPreference($preferenceData)
    {
        try {
            Log::info('Access Token:', ['token' => $this->accessToken]);
            
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Content-Type' => 'application/json'
            ])->post($this->baseUrl . '/checkout/preferences', $preferenceData);

            Log::info('MercadoPago API Response:', [
                'status' => $response->status(),
                'body' => $response->json()
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('MercadoPago Error:', $response->json());
            throw new \Exception('Error creating preference: ' . json_encode($response->json()));
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
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Content-Type' => 'application/json'
            ])->get($this->baseUrl . "/v1/payments/{$paymentId}");

            if ($response->successful()) {
                return $response->json();
            }

            throw new \Exception('Error getting payment info: ' . json_encode($response->json()));
        } catch (\Exception $e) {
            Log::error('Error getting payment info:', [
                'message' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}