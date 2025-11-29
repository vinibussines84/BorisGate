<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class ReflowPayService
{
    protected $baseUrl;
    protected $apiKey;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.reflowpay.base_url'), '/');
        $this->apiKey  = config('services.reflowpay.api_key');
    }

    public function createCashIn(array $payload)
    {
        $url = $this->baseUrl . '/transaction/qrcode/cashin';

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json'
        ])->post($url, $payload);

        if ($response->failed()) {
            throw new \Exception(
                "Erro na SafePayments: " . $response->body()
            );
        }

        return $response->json();
    }
}
