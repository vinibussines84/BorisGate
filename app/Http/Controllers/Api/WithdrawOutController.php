<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SendWebhookWithdrawCreatedJob;
use App\Models\User;
use App\Models\Withdraw;
use App\Services\Pix\KeyValidator;
use App\Services\Withdraw\WithdrawService;
use App\Services\Provider\ProviderColdFyOut;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use App\Support\StatusMap;

class WithdrawOutController extends Controller
{
    public function __construct(
        private readonly WithdrawService $withdrawService,
    ) {}

    public function store(Request $request)
    {
        try {
            /*
            |--------------------------------------------------------------------------
            | 1) Autenticação
            |--------------------------------------------------------------------------
            */
            $authKey   = $request->header('X-Auth-Key');
            $secretKey = $request->header('X-Secret-Key');

            if (!$authKey || !$secretKey) {
                return $this->error("Headers ausentes. É necessário enviar X-Auth-Key e X-Secret-Key.");
            }

            $user = User::where('authkey', $authKey)
                ->where('secretkey', $secretKey)
                ->first();

            if (!$user) {
                return $this->error("Credenciais inválidas.");
            }

            /*
            |--------------------------------------------------------------------------
            | 2) Validação
            |--------------------------------------------------------------------------
            */
            $data = $request->validate([
                'amount'       => ['required', 'numeric', 'min:0.01'],
                'key'          => ['required', 'string'],
                'key_type'     => ['required', Rule::in(['cpf','cnpj','email','phone','evp'])],
                'description'  => ['nullable','string','max:255'],
                'external_id'  => ['nullable','string','max:64'],
            ]);

            $gross = (float) $data['amount'];

            if ($gross < 10) {
                return $this->error("Valor mínimo para saque é R$ 10,00.");
            }

            if (!$user->tax_out_enabled) {
                return $this->error("Cashout desabilitado para este usuário.");
            }

            /*
            |--------------------------------------------------------------------------
            | 3) Chave PIX e normalização
            |--------------------------------------------------------------------------
            */
            $rawKeyType = strtolower($data['key_type']);
            $key = trim($data['key']);

            if ($rawKeyType === 'phone') {
                $phone = preg_replace('/\D/', '', $key);
                if (str_starts_with($phone, '55')) {
                    $phone = substr($phone, 2);
                }
                $key = $phone;
            }

            $keyTypeForValidation = match ($rawKeyType) {
                'random', 'evp' => 'evp',
                default => $rawKeyType,
            };

            if (!KeyValidator::validate($key, strtoupper($keyTypeForValidation))) {
                return $this->error("Chave PIX inválida.");
            }

            /*
            |--------------------------------------------------------------------------
            | 4) Identificação e idempotência
            |--------------------------------------------------------------------------
            */
            $externalId = $data['external_id']
                ?: 'WD_' . now()->timestamp . '_' . rand(1000, 9999);

            if (
                Withdraw::where('user_id', $user->id)
                ->where('external_id', $externalId)
                ->exists()
            ) {
                return $this->error("External ID duplicado. Este saque já foi processado.");
            }

            $internalRef = 'withdraw_' . now()->timestamp . '_' . rand(1000, 9999);

            /*
            |--------------------------------------------------------------------------
            | 5) Criar saque local (sem alterar status)
            |--------------------------------------------------------------------------
            */
            $withdraw = $this->withdrawService->create(
                $user,
                $gross,
                $gross,
                0.0,
                [
                    'key'         => $key,
                    'key_type'    => $rawKeyType,
                    'external_id' => $externalId,
                    'internal_ref'=> $internalRef,
                    'provider'    => 'coldfy',
                    'status'      => 'processing',
                ]
            );

            /*
            |--------------------------------------------------------------------------
            | 6) Envio ao provider ColdFy
            |--------------------------------------------------------------------------
            */
            $provider = new ProviderColdFyOut();

            $payload = [
                'pixkeytype'      => $rawKeyType,
                'pixkey'          => $key,
                'requestedamount' => (int) ($gross * 100), // em centavos
                'description'     => $data['description'] ?? 'Saque via API',
                'idempotency_key' => $externalId,
                'isPix'           => true,
                'postbackUrl'     => route('webhooks.coldfy'),
            ];

            $response = $provider->createCashout($payload);

            // Normaliza o status da ColdFy para o formato interno
            $remoteStatus = data_get($response, 'status', 'pending');
            $normalizedStatus = StatusMap::normalize($remoteStatus);

            Log::info('💸 ColdFy Cashout enviado', [
                'withdraw_id' => $withdraw->id,
                'remote_status' => $remoteStatus,
                'normalized_status' => $normalizedStatus,
                'response' => $response,
            ]);

            /*
            |--------------------------------------------------------------------------
            | 7) Webhook de criação (OUT)
            |--------------------------------------------------------------------------
            */
            if ($user->webhook_enabled && $user->webhook_out_url) {
                SendWebhookWithdrawCreatedJob::dispatch(
                    $user->id,
                    $withdraw->id,
                    'processing',
                    null
                )->onQueue('webhooks');
            }

            /*
            |--------------------------------------------------------------------------
            | 8) Retorno
            |--------------------------------------------------------------------------
            */
            return response()->json([
                'success' => true,
                'message' => 'Saque enviado para a ColdFy com sucesso.',
                'data' => [
                    'id'             => $withdraw->id,
                    'external_id'    => $externalId,
                    'requested'      => $gross,
                    'pix_key'        => $withdraw->pixkey,
                    'pix_key_type'   => $withdraw->pixkey_type,
                    'provider'       => 'coldfy',
                    'provider_status'=> $remoteStatus,
                    'system_status'  => $normalizedStatus,
                    'reference'      => data_get($response, 'id'),
                    'created_at'     => $withdraw->created_at->toIso8601String(),
                ],
            ]);

        } catch (\Throwable $e) {

            Log::error('🚨 Erro ao criar saque (ColdFy)', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->error("Erro interno ao processar o saque. Detalhe: {$e->getMessage()}");
        }
    }

    private function error(string $message)
    {
        return response()->json([
            'success' => false,
            'error'   => $message,
        ], 400);
    }
}
