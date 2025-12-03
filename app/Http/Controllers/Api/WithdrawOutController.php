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
        private readonly WithdrawService $withdrawService
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
                'key_type'     => ['required', Rule::in(['cpf','cnpj','email','phone','random'])],
                'description'  => ['nullable','string','max:255'],
                'external_id'  => ['nullable','string','max:64'],
            ]);

            /*
            |--------------------------------------------------------------------------
            | 4) Valor mínimo aceito
            |--------------------------------------------------------------------------
            */
            $gross = (float) $data['amount'];

            if ($gross < 10) {
                return $this->error("Valor mínimo para saque é R$ 10,00.");
            }

            /*
            |--------------------------------------------------------------------------
            | 5) Validação da chave PIX
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
            | 6) Taxa da Pluggou é absorvida
            |--------------------------------------------------------------------------
            */
            $pluggouFee = 0.20;
            $amountToSend = $gross + $pluggouFee;

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
            | 8) Criar saque LOCAL (rápido)
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
                ]
            );

            /*
            |--------------------------------------------------------------------------
            | 9) Criar payload p/ fila
            |--------------------------------------------------------------------------
            */
            $payload = [
                "amount"    => (int) round($amountToSend * 100),
                "key_type"  => strtolower($data['key_type']),
                "key_value" => $data['key'],
            ];

            /*
            |--------------------------------------------------------------------------
            | 10) Enviar para a FILA — não esperar a Pluggou
            |--------------------------------------------------------------------------
            */
            dispatch(new ProcessWithdrawJob($withdraw, $payload));

            /*
            |--------------------------------------------------------------------------
            | 11) Webhook OUT "withdraw.created"
            |--------------------------------------------------------------------------
            */
            if ($user->webhook_enabled && $user->webhook_out_url) {
                SendWebhookWithdrawCreatedJob::dispatch(
                    $user->id,
                    $withdraw->id,
                    'processing',
                    null
                );
            }

            /*
            |--------------------------------------------------------------------------
            | 12) Retorno imediato (SEM esperar a Pluggou)
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
                    'status'        => 'processing',
                    'reference'     => null,
                    'provider'      => 'Internal',
                ]
            ]);

        } catch (\Throwable $e) {

            Log::error('🚨 Erro ao criar saque', [
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
