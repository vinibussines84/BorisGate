<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessWithdrawJob;
use App\Jobs\SendWebhookWithdrawCreatedJob;
use App\Models\User;
use App\Models\Withdraw;
use App\Services\Pix\KeyValidator;
use App\Services\Lumnis\LumnisCashoutService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class WithdrawOutController extends Controller
{
    public function __construct(
        private readonly LumnisCashoutService $withdrawService
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

            /*
            |--------------------------------------------------------------------------
            | 6) Taxas
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
            | 8) Criar saque LOCAL
            |--------------------------------------------------------------------------
            */
            $withdraw = Withdraw::create([
                'user_id'        => $user->id,
                'gross_amount'   => $gross,
                'amount'         => $net,
                'fee'            => $fee,
                'status'         => 'processing',
                'pixkey'         => $data['key'],
                'pixkey_type'    => $data['key_type'],
                'external_id'    => $externalId,
                'meta' => [
                    'internal_reference' => $internalRef,
                    'provider' => 'lumnis',
                ],
            ]);

            /*
            |--------------------------------------------------------------------------
            | 9) PAYLOAD CORRETO PARA LUMNIS
            |--------------------------------------------------------------------------
            */
            $payload = [
                "amount"       => (int) round($gross * 100),
                "key"          => $data['key'],
                "key_type"     => strtoupper($data['key_type']),
                "description"  => $data['description'] ?? "Withdraw",
                "details"      => [
                    "name"     => $user->name,
                    "document" => $user->document ?? "00000000000",
                ],
                "postback"     => $user->webhook_out_url,
                "external_ref" => $externalId,
            ];

            /*
            |--------------------------------------------------------------------------
            | 10) FILA - não esperar Lumnis
            |--------------------------------------------------------------------------
            */
            dispatch(new ProcessWithdrawJob($withdraw, $payload));

            /*
            |--------------------------------------------------------------------------
            | 11) Webhook OUT
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
            | 12) Resposta imediata
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
