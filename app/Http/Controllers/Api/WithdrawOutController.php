<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SendWebhookWithdrawCreatedJob;
use App\Models\User;
use App\Models\Withdraw;
use App\Services\Pix\KeyValidator;
use App\Services\Pluggou\PluggouCashoutService;
use App\Services\Withdraw\WithdrawService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class WithdrawOutController extends Controller
{
    public function __construct(
        private readonly WithdrawService $withdrawService,
        private readonly PluggouCashoutService $pluggouService,
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
            | 2) Normalização da key_type e formatação
            |--------------------------------------------------------------------------
            */
            $keyType = strtolower($request->input('key_type'));
            $key     = $request->input('key');

            if ($keyType === 'phone') {
                $phone = preg_replace('/\D/', '', $key);
                if (str_starts_with($phone, '55')) {
                    $phone = substr($phone, 2);
                }
                $key = $phone;
            }

            $request->merge([
                'key'      => $key,
                'key_type' => $keyType
            ]);

            /*
            |--------------------------------------------------------------------------
            | 3) Validação
            |--------------------------------------------------------------------------
            */
            $data = $request->validate([
                'amount'       => ['required', 'numeric', 'min:0.01'],
                'key'          => ['required', 'string'],
                'key_type'     => ['required', Rule::in(['cpf','cnpj','email','phone','random'])],
                'description'  => ['nullable','string','max:255'],
                'external_id'  => ['nullable','string','max:64'],
            ]);

            /*
            |--------------------------------------------------------------------------
            | 4) Valor mínimo
            |--------------------------------------------------------------------------
            */
            $gross = (float) $data['amount'];
            if ($gross < 10) {
                return $this->error("Valor mínimo para saque é R$ 10,00.");
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
            | 6) Taxas — (mantemos compatível)
            |--------------------------------------------------------------------------
            */
            $fee = 0;
            $net = $gross;

            /*
            |--------------------------------------------------------------------------
            | 7) Idempotência
            |--------------------------------------------------------------------------
            */
            $externalId = $data['external_id']
                ?: 'WD_' . now()->timestamp . '_' . rand(1000,9999);

            if (Withdraw::where('user_id',$user->id)
                ->where('external_id',$externalId)
                ->exists()) {
                return $this->error("External ID duplicado.");
            }

            $internalRef = 'withdraw_' . now()->timestamp . '_' . rand(1000,9999);

            /*
            |--------------------------------------------------------------------------
            | 8) Criar saque local
            |--------------------------------------------------------------------------
            */
            $withdraw = $this->withdrawService->create(
                $user,
                $gross,
                $net,
                $fee,
                [
                    'key'         => $data['key'],
                    'key_type'    => $data['key_type'],
                    'external_id' => $externalId,
                    'internal_ref'=> $internalRef,
                    'provider'    => 'pluggou',
                    'status'      => 'processing',
                ]
            );

            /*
            |--------------------------------------------------------------------------
            | 9) Payload Pluggou
            |--------------------------------------------------------------------------
            */
            $payload = [
                "amount"     => (int) round($gross * 100), // centavos
                "key_type"   => strtolower($data['key_type']),
                "key_value"  => preg_replace('/\D+/', '', $data['key']),
            ];

            /*
            |--------------------------------------------------------------------------
            | 10) Enviar requisição diretamente à Pluggou
            |--------------------------------------------------------------------------
            */
            $response = $this->pluggouService->createCashout($payload);

            if (isset($response['success']) && $response['success'] === true) {
                $withdraw->update([
                    'status' => 'processing',
                    'provider_reference' => $response['data']['id'] ?? null,
                    'meta' => array_merge($withdraw->meta ?? [], [
                        'pluggou_payload' => $payload,
                        'pluggou_response' => $response,
                    ]),
                ]);
            } else {
                $withdraw->update(['status' => 'failed']);
                Log::error('❌ Saque Pluggou falhou', ['response' => $response]);
            }

            /*
            |--------------------------------------------------------------------------
            | 11) Webhook OUT imediato
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
            | 12) Resposta final
            |--------------------------------------------------------------------------
            */
            return response()->json([
                'success' => true,
                'message' => 'Saque enviado para processamento.',
                'data' => [
                    'id'            => $withdraw->id,
                    'external_id'   => $externalId,
                    'amount'        => $withdraw->gross_amount,
                    'liquid_amount' => $withdraw->amount,
                    'pix_key'       => $withdraw->pixkey,
                    'pix_key_type'  => $withdraw->pixkey_type,
                    'status'        => $withdraw->status,
                    'reference'     => $withdraw->provider_reference,
                    'provider'      => 'Pluggou',
                ]
            ]);

        } catch (\Throwable $e) {
            Log::error('🚨 Erro ao criar saque (Pluggou)', [
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
        ], 400);
    }
}
