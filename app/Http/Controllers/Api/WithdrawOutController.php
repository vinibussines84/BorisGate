<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessWithdrawJob;
use App\Jobs\SendWebhookWithdrawCreatedJob;
use App\Models\User;
use App\Models\Withdraw;
use App\Services\Pix\KeyValidator;
use App\Services\Withdraw\WithdrawService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class WithdrawOutController extends Controller
{
    public function __construct(
        private readonly WithdrawService $withdrawService,
    ) {}

    public function store(Request $request)
    {
        try {

            /* ===============================================================
             | 1) Autenticação
             ===============================================================*/
            $authKey   = $request->header('X-Auth-Key');
            $secretKey = $request->header('X-Secret-Key');

            if (!$authKey || !$secretKey) {
                return $this->error('Headers ausentes. Informe X-Auth-Key e X-Secret-Key.');
            }

            $user = User::where('authkey', $authKey)
                ->where('secretkey', $secretKey)
                ->first();

            if (!$user) {
                return $this->error('Credenciais inválidas.');
            }

            /* ===============================================================
             | 2) Validação
             ===============================================================*/
            $data = $request->validate([
                'amount'      => ['required', 'numeric', 'min:10'],
                'key'         => ['required', 'string'],
                'key_type'    => ['required', Rule::in(['cpf','cnpj','email','phone','evp'])],
                'description' => ['nullable','string','max:255'],
                'external_id' => ['nullable','string','max:64'],
            ]);

            /* ===============================================================
             | 3) Normalização da chave PIX
             ===============================================================*/
            $rawKeyType = strtolower($data['key_type']);
            $key        = trim($data['key']);

            if ($rawKeyType === 'phone') {
                $key = preg_replace('/\D/', '', $key);
                if (str_starts_with($key, '55')) {
                    $key = substr($key, 2);
                }
            }

            /* ===============================================================
             | 4) Validação da chave PIX
             ===============================================================*/
            $keyTypeForValidation = match ($rawKeyType) {
                'evp' => 'EVP',
                default => strtoupper($rawKeyType),
            };

            if (!KeyValidator::validate($key, $keyTypeForValidation)) {
                return $this->error('Chave PIX inválida.');
            }

            /* ===============================================================
             | 5) Regras de negócio
             ===============================================================*/
            if (!$user->tax_out_enabled) {
                return $this->error('Cashout desabilitado para este usuário.');
            }

            $gross = (float) $data['amount'];

            /* ===============================================================
             | 6) Idempotência
             ===============================================================*/
            $externalId = $data['external_id']
                ?: 'WD_' . now()->timestamp . '_' . random_int(1000, 9999);

            if (
                Withdraw::where('user_id', $user->id)
                    ->where('external_id', $externalId)
                    ->exists()
            ) {
                return $this->error('External ID duplicado. Saque já processado.');
            }

            $internalRef = 'withdraw_' . now()->timestamp . '_' . random_int(1000, 9999);

            /* ===============================================================
             | 7) Criar saque local (PENDING)
             ===============================================================*/
            $withdraw = $this->withdrawService->create(
                $user,
                $gross,        // amount (líquido)
                $gross,        // gross_amount
                0,             // fee_amount
                [
                    'pixkey'        => $key,
                    'pixkey_type'   => $rawKeyType,
                    'external_id'   => $externalId,
                    'idempotency_key'=> $internalRef,
                    'provider'      => 'xflow',
                    'status'        => Withdraw::STATUS_PENDING,
                    'description'   => $data['description'] ?? null,
                ]
            );

            /* ===============================================================
             | 8) Payload XFLOW (CORRETO)
             ===============================================================*/
            $keyTypeForProvider = match ($rawKeyType) {
                'cpf'   => 'CPF',
                'cnpj'  => 'CNPJ',
                'email' => 'EMAIL',
                'phone' => 'PHONE',
                'evp'   => 'EVP',
                default => throw new \Exception('Tipo de chave PIX não suportado.'),
            };

            $payload = [
                'amount'       => $gross,
                'external_id'  => $externalId,
                'pix_key'      => $key,
                'key_type'     => $keyTypeForProvider,
                'description' => $data['description'] ?? 'Saque solicitado via API',
            ];

            ProcessWithdrawJob::dispatch($withdraw, $payload)
                ->onQueue('withdraws');

            /* ===============================================================
             | 9) Webhook OUT
             ===============================================================*/
            if ($user->webhook_enabled && $user->webhook_out_url) {
                SendWebhookWithdrawCreatedJob::dispatch(
                    $user->id,
                    $withdraw->id,
                    Withdraw::STATUS_PENDING,
                    null
                )->onQueue('webhooks');
            }

            /* ===============================================================
             | 10) Resposta
             ===============================================================*/
            return response()->json([
                'success' => true,
                'message' => 'Saque enfileirado para processamento.',
                'data' => [
                    'id'           => $withdraw->id,
                    'external_id'  => $externalId,
                    'amount'       => $gross,
                    'pix_key'      => $withdraw->pixkey,
                    'pix_key_type' => $withdraw->pixkey_type,
                    'status'       => Withdraw::STATUS_PENDING,
                    'provider'     => 'xflow',
                    'created_at'   => $withdraw->created_at->toIso8601String(),
                ],
            ]);

        } catch (\Throwable $e) {
            Log::error('🚨 Erro ao criar saque (XFlow)', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            if (str_contains($e->getMessage(), 'Saldo insuficiente')) {
                return $this->error($e->getMessage());
            }

            return $this->error('Erro interno ao processar o saque.');
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
