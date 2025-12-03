<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SendWebhookWithdrawCreatedJob;
use App\Models\User;
use App\Models\Withdraw;
use App\Services\Pix\KeyValidator;
use App\Services\Withdraw\WithdrawService;
use App\Services\Pluggou\PluggouWithdrawService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class WithdrawOutController extends Controller
{
    public function __construct(
        private readonly WithdrawService         $withdrawService,
        private readonly PluggouWithdrawService  $pluggou
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
                return $this->error("Headers ausentes.");
            }

            $user = User::where('authkey', $authKey)
                ->where('secretkey', $secretKey)
                ->first();

            if (!$user) {
                return $this->error("Credenciais inválidas.");
            }

            /*
            |--------------------------------------------------------------------------
            | 2) Normalizações
            |--------------------------------------------------------------------------
            */
            $request->merge([
                'key_type' => strtolower($request->input('key_type')),
            ]);

            // Normalizar telefone
            if ($request->input('key_type') === 'phone') {

                $phone = preg_replace('/\D/', '', $request->input('key'));

                if (str_starts_with($phone, '55')) {
                    $phone = substr($phone, 2);
                }

                $request->merge(['key' => $phone]);
            }

            /*
            |--------------------------------------------------------------------------
            | 3) Validação
            |--------------------------------------------------------------------------
            */
            $data = $request->validate([
                'amount'       => ['required', 'numeric', 'min:0.01'],
                'key'          => ['required', 'string'],
                'key_type'     => ['required', Rule::in(['cpf', 'cnpj', 'email', 'phone', 'random'])],
                'description'  => ['nullable', 'string', 'max:255'],
                'external_id'  => ['nullable', 'string', 'max:64'],
            ]);

            /*
            |--------------------------------------------------------------------------
            | 4) Valor mínimo
            |--------------------------------------------------------------------------
            */
            $gross = (float) $data['amount'];

            if ($gross < 5) {
                return $this->error("Valor mínimo para saque é R$ 5,00.");
            }

            /*
            |--------------------------------------------------------------------------
            | 5) Validar chave PIX
            |--------------------------------------------------------------------------
            */
            if (!KeyValidator::validate($data['key'], strtoupper($data['key_type']))) {
                return $this->error("Chave PIX inválida.");
            }

            if (!$user->tax_out_enabled) {
                return $this->error("Cashout desabilitado.");
            }

            /*
            |--------------------------------------------------------------------------
            | 6) Calcular taxas
            |--------------------------------------------------------------------------
            */
            $fee = round(($user->tax_out_fixed ?? 0) + ($gross * ($user->tax_out_percent ?? 0) / 100), 2);
            $net = round($gross - $fee, 2);

            if ($net <= 0) {
                return $this->error("Valor líquido inválido.");
            }

            /*
            |--------------------------------------------------------------------------
            | 7) Idempotência (external_id)
            |--------------------------------------------------------------------------
            */
            $externalId = $data['external_id']
                ?: 'WD_' . now()->timestamp . '_' . random_int(1000, 9999);

            if (Withdraw::where('user_id', $user->id)
                ->where('external_id', $externalId)
                ->exists()) {
                return $this->error("External ID duplicado.");
            }

            $internalRef = 'withdraw_' . now()->timestamp . '_' . random_int(1000, 9999);

            /*
            |--------------------------------------------------------------------------
            | 8) Criar saque local
            |--------------------------------------------------------------------------
            */
            try {
                $withdraw = $this->withdrawService->create(
                    $user,
                    $gross,
                    $net,
                    $fee,
                    [
                        'key'         => $data['key'],
                        'key_type'    => strtolower($data['key_type']),
                        'external_id' => $externalId,
                        'internal_ref'=> $internalRef,
                        'provider'    => 'pluggou',
                    ]
                );
            } catch (\Throwable $e) {
                return $this->error($e->getMessage());
            }

            /*
            |--------------------------------------------------------------------------
            | 9) Payload oficial da PLUGGOU
            |--------------------------------------------------------------------------
            */
            $payload = [
                "amount"    => (int) round($gross * 100),
                "key_type"  => strtolower($data['key_type']),
                "key_value" => $data['key'],
            ];

            /*
            |--------------------------------------------------------------------------
            | 10) Cria saque na API da Pluggou
            |--------------------------------------------------------------------------
            */
            $resp = $this->pluggou->createWithdrawal($payload);

            /*
            |--------------------------------------------------------------------------
            | 11) Falha do provedor
            |--------------------------------------------------------------------------
            */
            if (!$resp['success']) {

                $reason = $resp['data']['message']
                    ?? ($resp['validation_errors'] ?? null)
                    ?? "Erro ao criar saque na Pluggou";

                $this->withdrawService->refundLocal($withdraw, 'provider_error');

                return $this->error($reason);
            }

            /*
            |--------------------------------------------------------------------------
            | 12) Extrair referência do provedor
            |--------------------------------------------------------------------------
            */
            $providerRef = data_get($resp, 'data.data.id');

            if (!$providerRef) {
                $this->withdrawService->refundLocal($withdraw, 'missing_provider_id');
                return $this->error("Não foi possível obter ID do saque na Pluggou.");
            }

            /*
            |--------------------------------------------------------------------------
            | 13) Normalizar status inicial
            |--------------------------------------------------------------------------
            */
            $providerStatus = strtolower(data_get($resp, 'data.data.status')) ?? 'pending';

            $status = match ($providerStatus) {
                'paid', 'success', 'completed' => 'paid',
                'failed', 'error', 'canceled', 'cancelled' => 'failed',
                'processing', 'sending', 'sent', 'pending' => 'processing',
                default => 'processing',
            };

            /*
            |--------------------------------------------------------------------------
            | 14) Atualizar saque local
            |--------------------------------------------------------------------------
            */
            $this->withdrawService->updateProviderReference(
                $withdraw,
                $providerRef,
                $status,
                $resp
            );

            /*
            |--------------------------------------------------------------------------
            | 15) Webhook OUT
            |--------------------------------------------------------------------------
            */
            if ($user->webhook_enabled && $user->webhook_out_url) {
                SendWebhookWithdrawCreatedJob::dispatch(
                    $user->id,
                    $withdraw->id,
                    $status,
                    $providerRef
                );
            }

            /*
            |--------------------------------------------------------------------------
            | 16) Retorno final
            |--------------------------------------------------------------------------
            */
            return response()->json([
                'success' => true,
                'message' => 'Saque solicitado com sucesso!',
                'data' => [
                    'id'            => $withdraw->id,
                    'external_id'   => $withdraw->external_id,
                    'amount'        => $withdraw->gross_amount,
                    'liquid_amount' => $withdraw->amount,
                    'pix_key'       => $withdraw->pixkey,
                    'pix_key_type'  => $withdraw->pixkey_type,
                    'status'        => $status,
                    'reference'     => $providerRef,
                    'provider'      => 'pluggou',
                ],
            ]);

        } catch (\Throwable $e) {

            Log::error('🚨 Erro ao criar saque com Pluggou', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->error("Erro interno ao processar saque.");
        }
    }

    private function error(string $message)
    {
        return response()->json([
            'success' => false,
            'error'   => $message,
        ]);
    }
}
