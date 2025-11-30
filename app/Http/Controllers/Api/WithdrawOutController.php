<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Withdraw;
use App\Services\Pix\KeyValidator;
use App\Services\PodPay\PodPayCashoutService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class WithdrawOutController extends Controller
{
    public function __construct(
        private readonly PodPayCashoutService $podpay
    ) {}

    public function store(Request $request)
    {
        try {

            /* ============================================================
             * 🔐 Autenticação via Headers
             * ============================================================ */
            $authKey   = $request->header('X-Auth-Key');
            $secretKey = $request->header('X-Secret-Key');

            if (!$authKey || !$secretKey) {
                return $this->error('Headers ausentes.');
            }

            $user = User::where('authkey', $authKey)
                ->where('secretkey', $secretKey)
                ->first();

            if (!$user) {
                return $this->error('Credenciais inválidas.');
            }

            /* ============================================================
             * 🔤 Normalizar key_type
             * ============================================================ */
            $request->merge(['key_type' => strtolower($request->input('key_type'))]);

            /* ============================================================
             * 🧾 Validação
             * ============================================================ */
            $data = $request->validate([
                'amount'   => ['required', 'numeric', 'min:0.01'],
                'key'      => ['required', 'string'],
                'key_type' => ['required', Rule::in(['cpf','cnpj','email','phone','evp','copypaste'])],
                'description' => ['nullable', 'string', 'max:255'],
                'external_id' => ['nullable', 'string', 'max:64'],
            ]);

            /* ============================================================
             * 💸 Valor mínimo
             * ============================================================ */
            $gross = (float) $data['amount'];

            if ($gross < 5) {
                return $this->error('Valor mínimo para saque é R$ 5,00.');
            }

            /* ============================================================
             * 🔎 Validar chave PIX
             * ============================================================ */
            if (!KeyValidator::validate($data['key'], strtoupper($data['key_type']))) {
                return $this->error('Chave PIX inválida para o tipo informado.');
            }

            if (!$user->tax_out_enabled) {
                return $this->error('Cashout desabilitado.');
            }

            /* ============================================================
             * 💰 Cálculo de Taxas
             * ============================================================ */
            $fee = round(($user->tax_out_fixed ?? 0) + ($gross * ($user->tax_out_percent ?? 0) / 100), 2);
            $net = round($gross - $fee, 2);

            if ($net <= 0) {
                return $this->error('Valor líquido inválido.');
            }

            /* ============================================================
             * 🔢 External ID
             * ============================================================ */
            $externalId = $data['external_id'] ??
                'WD_' . now()->timestamp . '_' . random_int(1000, 9999);

            if (Withdraw::where('user_id', $user->id)->where('external_id', $externalId)->exists()) {
                return $this->error('External ID duplicado.');
            }

            /* ============================================================
             * 🧾 Criar saque local + debitar saldo
             * ============================================================ */
            $internalRef = 'withdraw_' . now()->timestamp . '_' . random_int(1000, 9999);

            $result = DB::transaction(function () use ($user, $gross, $net, $fee, $data, $externalId, $internalRef) {

                $u = User::where('id', $user->id)->lockForUpdate()->first();

                if ($u->amount_available < $gross) {
                    return ['error' => 'Saldo insuficiente.'];
                }

                $u->amount_available -= $gross;
                $u->save();

                $withdraw = Withdraw::create([
                    'user_id'         => $u->id,
                    'amount'          => $net,
                    'gross_amount'    => $gross,
                    'fee_amount'      => $fee,
                    'pixkey'          => $data['key'],
                    'pixkey_type'     => strtolower($data['key_type']),
                    'status'          => 'pending',
                    'provider'        => 'podpay',
                    'external_id'     => $externalId,
                    'idempotency_key' => $internalRef,
                    'meta' => [
                        'internal_reference' => $internalRef,
                        'tax_fixed'   => $u->tax_out_fixed,
                        'tax_percent' => $u->tax_out_percent,
                        'api_request' => true,
                    ],
                ]);

                return ['withdraw' => $withdraw];
            });

            if (isset($result['error'])) {
                return $this->error($result['error']);
            }

            $withdraw = $result['withdraw'];

            /* ============================================================
             * 2️⃣ Criar saque na PodPay
             * ============================================================ */
            $payload = [
                "method"      => "fiat",
                "amount"      => (int) round($gross * 100),
                "netPayout"   => false,
                "pixKey"      => $data['key'],
                "pixKeyType"  => strtolower($data['key_type']),
                "postbackUrl" => route('webhooks.podpay.withdraw'),
            ];

            $resp = $this->podpay->createWithdrawal($payload);

            if (!$resp['success']) {
                $this->refund($user, $gross, $withdraw, $resp);
                return $this->error('Falha ao criar saque.');
            }

            /* ============================================================
             * 3️⃣ Capturar ID do provider
             * ============================================================ */
            $providerReference = data_get($resp, 'data.id');

            if (!$providerReference) {
                $this->refund($user, $gross, $withdraw, 'missing_provider_id');
                return $this->error('Erro ao obter referência do saque.');
            }

            /* ============================================================
             * 🟦 NORMALIZAR STATUS DA PODPAY
             * ============================================================ */
            $providerStatus = strtoupper(data_get($resp, 'data.status', 'PENDING'));

            $status = match ($providerStatus) {
                'PENDING', 'PENDING_QUEUE' => 'pending',
                'PROCESSING', 'SENDING WITHDRAW REQUEST', 'SENT TO PROVIDER' => 'processing',
                'PAID', 'COMPLETED' => 'paid',
                'FAILED', 'ERROR', 'CANCELED' => 'failed',
                default => 'pending',
            };

            /* ============================================================
             * 4️⃣ Atualizar saque no banco
             * ============================================================ */
            DB::transaction(function () use ($withdraw, $providerReference, $status, $resp) {
                $withdraw->update([
                    'provider_reference' => $providerReference,
                    'status'             => $status,
                    'meta' => array_merge($withdraw->meta ?? [], [
                        'podpay_response' => $resp,
                    ]),
                ]);
            });

            /* ============================================================
             * 5️⃣ Webhook externo para o cliente
             * ============================================================ */
            if ($user->webhook_enabled && $user->webhook_out_url) {
                Http::post($user->webhook_out_url, [
                    'event' => 'withdraw.created',
                    'data' => [
                        'id'            => $withdraw->id,
                        'external_id'   => $withdraw->external_id,
                        'amount'        => $withdraw->gross_amount,
                        'liquid_amount' => $withdraw->amount,
                        'pix_key'       => $withdraw->pixkey,
                        'pix_key_type'  => $withdraw->pixkey_type,
                        'status'        => $status,
                        'reference'     => $providerReference,
                    ],
                ]);
            }

            /* ============================================================
             * 🟢 Sucesso
             * ============================================================ */
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
                    'reference'     => $providerReference,
                ],
            ]);

        } catch (\Throwable $e) {

            Log::error('🚨 Erro ao criar saque', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            if (isset($withdraw)) {
                $this->refund($user ?? null, $gross ?? 0, $withdraw, $e->getMessage());
            }

            return $this->error('Erro interno ao processar saque.');
        }
    }

    /**
     * 🔁 Reembolso + marca falha
     */
    private function refund(?User $user, float $gross, Withdraw $withdraw, $error = null)
    {
        if ($user) {
            DB::transaction(function () use ($user, $gross) {
                $u = User::where('id', $user->id)->lockForUpdate()->first();
                $u->amount_available += $gross;
                $u->save();
            });
        }

        $withdraw->update([
            'status' => 'failed',
            'meta'   => array_merge($withdraw->meta ?? [], ['error' => $error]),
        ]);

        Log::warning('💸 Reembolso após falha no saque ', [
            'user_id'     => $user?->id,
            'withdraw_id' => $withdraw->id,
            'reason'      => $error,
        ]);
    }

    /**
     * 🧩 Resposta padrão
     */
    private function error(string $message)
    {
        return response()->json([
            'success' => false,
            'error'   => $message,
        ]);
    }
}
