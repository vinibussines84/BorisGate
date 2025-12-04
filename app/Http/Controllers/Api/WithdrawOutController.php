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
            | 2) Normalização e padronização da chave PIX
            |--------------------------------------------------------------------------
            */
            $keyType = strtolower($request->input('key_type'));
            $key     = trim($request->input('key'));

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
            | 3) Validação de entrada
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
            | 4) Regras de negócio
            |--------------------------------------------------------------------------
            */
            $gross = (float) $data['amount']; // valor solicitado pelo cliente

            if ($gross < 10) {
                return $this->error("Valor mínimo para saque é R$ 10,00.");
            }

            if (!KeyValidator::validate($data['key'], strtoupper($data['key_type']))) {
                return $this->error("Chave PIX inválida.");
            }

            if (!$user->tax_out_enabled) {
                return $this->error("Cashout desabilitado para este usuário.");
            }

            /*
            |--------------------------------------------------------------------------
            | 5) Aqui entra a TAXA FIXA DA PLUGGOU
            |--------------------------------------------------------------------------
            |
            | Pluggou cobra R$ 0,30.
            | O cliente deve receber o valor inteiro, então nós aumentamos o valor enviado.
            |
            */
            $providerFeeFixed = 0.30;

            // Valor que enviaremos para a Pluggou
            $amountForProvider = $gross + $providerFeeFixed;

            // Valor líquido recebido pelo cliente permanece o solicitado
            $net = $gross;
            $fee = 0; // você banca a taxa

            /*
            |--------------------------------------------------------------------------
            | 6) Controle de idempotência
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
            | 7) Criar saque local + debitar saldo
            |--------------------------------------------------------------------------
            */
            $withdraw = $this->withdrawService->create(
                $user,
                $gross, // valor solicitado
                $net,   // liquido recebido pelo cliente
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
            | 8) Formatar chave PIX
            |--------------------------------------------------------------------------
            */
            $formattedKey = match ($data['key_type']) {
                'cpf', 'cnpj', 'phone' => preg_replace('/\D/', '', $data['key']),
                default                => trim($data['key']),
            };

            /*
            |--------------------------------------------------------------------------
            | 9) Payload para a Pluggou (com valor ajustado)
            |--------------------------------------------------------------------------
            */
            $payload = [
                "amount"      => (int) round($amountForProvider * 100), // ajustado!
                "key_type"    => strtolower($data['key_type']),
                "key_value"   => $formattedKey,
                "description" => $data['description'] ?? 'Saque via API',
            ];

            /*
            |--------------------------------------------------------------------------
            | 10) Enfileirar job
            |--------------------------------------------------------------------------
            */
            ProcessWithdrawJob::dispatch($withdraw, $payload)->onQueue('withdraws');

            /*
            |--------------------------------------------------------------------------
            | 11) Webhook imediato
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
            | 12) Retorno ao cliente
            |--------------------------------------------------------------------------
            */
            return response()->json([
                'success' => true,
                'message' => 'Saque enfileirado para processamento.',
                'data' => [
                    'id'             => $withdraw->id,
                    'external_id'    => $externalId,
                    'requested'      => $gross,
                    'sent_to_provider' => $amountForProvider,
                    'liquid_amount'  => $withdraw->amount,
                    'pix_key'        => $withdraw->pixkey,
                    'pix_key_type'   => $withdraw->pixkey_type,
                    'status'         => 'processing',
                    'reference'      => null,
                    'provider'       => 'Pluggou',
                    'created_at'     => $withdraw->created_at->toIso8601String(),
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
