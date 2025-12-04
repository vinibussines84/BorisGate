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
                'key_type'     => ['required', Rule::in(['cpf','cnpj','email','phone','random','evp'])],
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
            | 9) Formatar chave PIX corretamente
            |--------------------------------------------------------------------------
            */
            $formattedKey = match ($data['key_type']) {
                'cpf', 'cnpj', 'phone' => preg_replace('/\D/', '', $data['key']),
                default                => trim($data['key']),
            };

            /*
            |--------------------------------------------------------------------------
            | 10) Payload Pluggou
            |--------------------------------------------------------------------------
            */
            $payload = [
                "amount"     => (int) round($gross * 100), // em centavos
                "key_type"   => strtolower($data['key_type']),
                "key_value"  => $formattedKey,
            ];

            /*
            |--------------------------------------------------------------------------
            | 11) Enfileirar o processamento (não travar request)
            |--------------------------------------------------------------------------
            */
            ProcessWithdrawJob::dispatch($withdraw, $payload)->onQueue('withdraws');

            /*
            |--------------------------------------------------------------------------
            | 12) Webhook OUT imediato
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
            | 13) Resposta final instantânea
            |--------------------------------------------------------------------------
            */
            return response()->json([
                'success' => true,
                'message' => 'Saque enfileirado para processamento.',
                'data' => [
                    'id'            => $withdraw->id,
                    'external_id'   => $externalId,
                    'amount'        => $withdraw->gross_amount,
                    'liquid_amount' => $withdraw->amount,
                    'pix_key'       => $withdraw->pixkey,
                    'pix_key_type'  => $withdraw->pixkey_type,
                    'status'        => 'processing',
                    'reference'     => null,
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
