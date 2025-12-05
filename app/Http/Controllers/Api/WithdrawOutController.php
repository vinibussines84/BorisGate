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
            | 2) Normalização da chave PIX
            |--------------------------------------------------------------------------
            */

            $rawKeyType = strtolower($request->input('key_type'));
            $key       = trim($request->input('key'));

            // Normaliza telefone
            if ($rawKeyType === 'phone') {
                $phone = preg_replace('/\D/', '', $key);

                if (str_starts_with($phone, '55')) {
                    $phone = substr($phone, 2);
                }

                $key = $phone;
            }

            /*
            |--------------------------------------------------------------------------
            | 3) Converter key_type para validação
            |    random → evp (porque KeyValidator só valida EVP)
            |    evp → evp
            |--------------------------------------------------------------------------
            */
            $keyTypeForValidation = match ($rawKeyType) {
                'random' => 'evp',
                'evp'    => 'evp',
                default  => $rawKeyType,
            };

            /*
            |--------------------------------------------------------------------------
            | 4) Validação
            |--------------------------------------------------------------------------
            */
            $data = $request->validate([
                'amount'       => ['required', 'numeric', 'min:0.01'],
                'key'          => ['required', 'string'],
                'key_type'     => ['required', Rule::in(['cpf','cnpj','email','phone','random','evp'])],
                'description'  => ['nullable','string','max:255'],
                'external_id'  => ['nullable','string','max:64'],
            ]);

            /*
            |--------------------------------------------------------------------------
            | 5) Validar chave PIX
            |--------------------------------------------------------------------------
            */
            if (!KeyValidator::validate($key, strtoupper($keyTypeForValidation))) {
                return $this->error("Chave PIX inválida.");
            }

            /*
            |--------------------------------------------------------------------------
            | 6) Regras de negócio
            |--------------------------------------------------------------------------
            */
            $gross = (float) $data['amount'];

            if ($gross < 10) {
                return $this->error("Valor mínimo para saque é R$ 10,00.");
            }

            if (!$user->tax_out_enabled) {
                return $this->error("Cashout desabilitado para este usuário.");
            }

            /*
            |--------------------------------------------------------------------------
            | 7) Taxas (você banca R$ 0,20)
            |--------------------------------------------------------------------------
            */
            $providerFeeFixed = 0.20;
            $amountForProvider = $gross + $providerFeeFixed;
            $net = $gross;
            $fee = 0;

            /*
            |--------------------------------------------------------------------------
            | 8) Idempotência
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
            | 9) Criar saque local
            |--------------------------------------------------------------------------
            */
            $withdraw = $this->withdrawService->create(
                $user,
                $gross,
                $net,
                $fee,
                [
                    'key'         => $key,
                    'key_type'    => $rawKeyType,
                    'external_id' => $externalId,
                    'internal_ref'=> $internalRef,
                    'provider'    => 'pluggou',
                    'status'      => 'processing',
                ]
            );

            /*
            |--------------------------------------------------------------------------
            | 10) Converter para Pluggou
            |     evp ou random → SEMPRE "random"
            |--------------------------------------------------------------------------
            */
            $keyTypeForProvider = match ($rawKeyType) {
                'evp', 'random' => 'random',
                default          => $rawKeyType,
            };

            $formattedKey = match ($keyTypeForProvider) {
                'cpf', 'cnpj', 'phone' => preg_replace('/\D/', '', $key),
                default                => trim($key),
            };

            /*
            |--------------------------------------------------------------------------
            | 11) Payload para Pluggou
            |--------------------------------------------------------------------------
            */
            $payload = [
                "amount"      => (int) round($amountForProvider * 100),
                "key_type"    => $keyTypeForProvider,
                "key_value"   => $formattedKey,
                "description" => $data['description'] ?? 'Saque via API',
            ];

            ProcessWithdrawJob::dispatch($withdraw, $payload)->onQueue('withdraws');

            /*
            |--------------------------------------------------------------------------
            | 12) Webhook OUT
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
            | 13) Resposta
            |--------------------------------------------------------------------------
            */
            return response()->json([
                'success' => true,
                'message' => 'Saque enfileirado para processamento.',
                'data' => [
                    'id'               => $withdraw->id,
                    'external_id'      => $externalId,
                    'requested'        => $gross,
                    'sent_to_provider' => $amountForProvider,
                    'liquid_amount'    => $withdraw->amount,
                    'pix_key'          => $withdraw->pixkey,
                    'pix_key_type'     => $withdraw->pixkey_type,
                    'status'           => 'processing',
                    'reference'        => null,
                    'provider'         => 'Pluggou',
                    'created_at'       => $withdraw->created_at->toIso8601String(),
                ]
            ]);

        } catch (\Throwable $e) {

            Log::error('🚨 Erro ao criar saque (Pluggou)', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            if (str_contains($e->getMessage(), 'Saldo insuficiente')) {
                return $this->error($e->getMessage());
            }

            return $this->error("Erro interno ao processar o saque. Tente novamente mais tarde.");
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
