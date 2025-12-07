<?php

namespace App\Services\Provider;

use Illuminate\Support\Facades\Log;

class ProviderColdfyPay
{
    protected ColdfyPayService $api;

    public function __construct()
    {
        $this->api = new ColdfyPayService();
    }

    /**
     * --------------------------------------------------------
     *  Criar PIX (compatível com ProviderService)
     * --------------------------------------------------------
     */
    public function createPix(float $amount, array $payer)
    {
        $payload = [
            'name'          => $payer['name'] ?? null,
            'document'      => $payer['document'] ?? null,
            'document_type' => 'CPF',
            'email'         => $payer['email'] ?? 'noemail@example.com',
            'phone'         => $payer['phone'] ?? '00000000000',
            'amount'        => $amount,
            'title'         => 'PIX',
            'postbackUrl'   => $payer['postback_url'] ?? null,
        ];

        $response = $this->api->createPix($payload);

        if (!$response['success']) {
            throw new \Exception($response['error']);
        }

        $data = $response['data'];

        return [
            'id'         => $data['id'] ?? null,
            'qr_code'    => $data['pix']['qrcode'] ?? null,
            'raw'        => $data,
        ];
    }

    /**
     * --------------------------------------------------------
     *  Consultar status da transação
     *  ⚠ A API da ColdfyPay NÃO possui endpoint público de consulta
     * --------------------------------------------------------
     */
    public function getTransactionStatus(string $transactionId)
    {
        Log::warning("COLDFY_NO_STATUS_ENDPOINT", [
            'id' => $transactionId
        ]);

        return [
            'status' => 'unknown',
            'message' => 'ColdfyPay não possui endpoint de consulta de status.'
        ];
    }

    /**
     * --------------------------------------------------------
     *  Saque
     *  ⚠ ColdfyPay NÃO possui endpoint de saque
     * --------------------------------------------------------
     */
    public function withdraw(float $amount, array $recipient)
    {
        Log::warning("COLDFY_WITHDRAW_NOT_SUPPORTED");

        return [
            'success' => false,
            'error'   => 'ColdFyPay não suporta operações de saque.'
        ];
    }

    /**
     * --------------------------------------------------------
     *  Processar Webhook
     * --------------------------------------------------------
     */
    public function processWebhook(array $payload)
    {
        Log::info("COLDFY_WEBHOOK_RECEIVED", $payload);

        return [
            'received' => true,
            'payload'  => $payload,
        ];
    }
}
