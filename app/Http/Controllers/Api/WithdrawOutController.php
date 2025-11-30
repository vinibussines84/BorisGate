<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Withdraw;
use App\Services\Pix\KeyValidator;
use App\Services\Lumnis\LumnisCashoutService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class WithdrawOutController extends Controller
{
    public function __construct(
        private readonly LumnisCashoutService $lumnis
    ) {}

    public function store(Request $request)
    {
        /* ============================================================
         * 🔐 Autenticação
         * ============================================================ */
        $authKey   = $request->header('X-Auth-Key');
        $secretKey = $request->header('X-Secret-Key');

        if (!$authKey || !$secretKey) {
            return response()->json(['success' => false, 'error' => 'Headers ausentes.'], 401);
        }

        $user = User::where('authkey', $authKey)
            ->where('secretkey', $secretKey)
            ->first();

        if (!$user) {
            return response()->json(['success' => false, 'error' => 'Credenciais inválidas.'], 401);
        }

        /* ============================================================
         * 🧾 Validação
         * ============================================================ */
        $data = $request->validate([
            'amount'            => ['required', 'numeric', 'min:0.01'],
            'key'               => ['required', 'string'],
            'key_type'          => ['required', Rule::in(['EVP', 'EMAIL', 'PHONE', 'CPF', 'CNPJ'])],
            'description'       => ['nullable', 'string', 'max:255'],
            'details.name'      => ['required', 'string', 'max:100'],
            'details.document'  => ['required', 'string', 'max:20'],
            'external_id'       => ['sometimes', 'string', 'max:64'],
        ]);

        /* ============================================================
         * 🔎 Validação PIX
         * ============================================================ */
        if (!KeyValidator::validate($data['key'], $data['key_type'])) {
            return response()->json([
                'success' => false,
                'error'   => 'Chave Pix inválida para o tipo informado.'
            ], 422);
        }

        if (!$user->tax_out_enabled) {
            return response()->json(['success' => false, 'error' => 'Cashout desabilitado.'], 403);
        }

        /* ============================================================
         * 💰 Taxas
         * ============================================================ */
        $gross = (float) $data['amount'];
        $fee   = round(($user->tax_out_fixed ?? 0) + ($gross * ($user->tax_out_percent ?? 0) / 100), 2);
        $net   = round($gross - $fee, 2);

        if ($net <= 0) {
            return response()->json(['success' => false, 'error' => 'Valor líquido inválido.'], 422);
        }

        /* ============================================================
         * 🔢 External ID
         * ============================================================ */
        $externalId = $data['external_id'] ?? 'WD_' . now()->timestamp . '_' . random_int(1000, 9999);

        if (Withdraw::where('user_id', $user->id)->where('external_id', $externalId)->exists()) {
            return response()->json(['success' => false, 'error' => 'Duplicate external_id.'], 409);
        }

        /* ============================================================
         * 🧾 1️⃣ Criação local e débito
         * ============================================================ */
        $internalRef = 'withdraw_' . now()->timestamp . '_' . random_int(1000, 9999);

        try {
            $result = DB::transaction(function () use ($user, $gross, $net, $fee, $data, $externalId, $internalRef) {

                $u = User::where('id', $user->id)->lockForUpdate()->first();

                if ($u->amount_available < $gross) {
                    return ['error' => ['success' => false, 'error' => 'Saldo insuficiente.']];
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
                    'provider'        => 'lumnis',
                    'external_id'     => $externalId,
                    'idempotency_key' => $internalRef,
                    'meta' => [
                        'internal_reference' => $internalRef,
                        'tax_fixed'          => $u->tax_out_fixed,
                        'tax_percent'        => $u->tax_out_percent,
                        'api_request'        => true,
                    ]
                ]);

                return ['withdraw' => $withdraw];
            });

            if (isset($result['error'])) {
                return response()->json($result['error'], 422);
            }

            $withdraw = $result['withdraw'];

            /* ============================================================
             * 2️⃣ Criar saque na Lumnis
             * ============================================================ */
            $payload = [
                "amount"      => (int) round($net * 100),
                "key"         => $data['key'],
                "key_type"    => strtoupper($data['key_type']),
                "description" => $data['description'] ?? 'Saque via API',
                "details"     => [
                    "name"     => $data['details']['name'],
                    "document" => $data['details']['document'],
                ],
                "postback"    => route('webhooks.lumnis.withdraw'),
            ];

            $resp = $this->lumnis->createWithdrawal($payload);

            if (!$resp['success']) {
                $this->refund($user, $gross, $withdraw, $resp);
                return response()->json(['success' => false, 'error' => 'Falha Lumnis'], 502);
            }

            /* ============================================================
             * 3️⃣ Capturar referência da Lumnis (corrigido)
             * ============================================================ */
            $providerReference =
                   data_get($resp, 'data.identifier')
                ?? data_get($resp, 'data.id')
                ?? data_get($resp, 'data.data.0.identifier')
                ?? data_get($resp, 'data.data.0.id')
                ?? null;

            if (!$providerReference) {
                Log::warning('⚠️ Lumnis: resposta sem identificador', ['response' => $resp]);
                $this->refund($user, $gross, $withdraw, 'missing_identifier');
                return response()->json(['error' => 'Missing identifier from Lumnis'], 502);
            }

            $status = strtolower(data_get($resp, 'data.status', 'pending'));

            /* ============================================================
             * 4️⃣ Atualiza DB com referência real
             * ============================================================ */
            DB::transaction(function () use ($withdraw, $providerReference, $status, $resp) {
                $withdraw->update([
                    'provider_reference' => $providerReference,
                    'status'             => $status,
                    'meta'               => array_merge($withdraw->meta ?? [], [
                        'lumnis_response' => $resp,
                    ]),
                ]);
            });

            /* ============================================================
             * 5️⃣ Enviar webhook OUT (opcional)
             * ============================================================ */
            if ($user->webhook_enabled && $user->webhook_out_url) {
                Http::post($user->webhook_out_url, [
                    'event' => 'withdraw.created',
                    'data' => [
                        'id'            => $withdraw->id,
                        'external_id'   => $withdraw->external_id,
                        'amount'        => $gross,
                        'liquid_amount' => $net,
                        'pix_key'       => $withdraw->pixkey,
                        'pix_key_type'  => $withdraw->pixkey_type,
                        'status'        => $status,
                        'reference'     => $providerReference,
                    ],
                ]);
            }

            /* ============================================================
             * ✅ Retorno final
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

            Log::error('🚨 Erro ao criar saque Lumnis', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            if (isset($withdraw)) {
                $this->refund($user, $gross, $withdraw, $e->getMessage());
            }

            return response()->json(['success' => false, 'error' => $e->getMessage()], 502);
        }
    }

    /**
     * 🔁 Estorna o valor e marca o saque como falho
     */
    private function refund(User $user, float $gross, Withdraw $withdraw, $error = null)
    {
        DB::transaction(function () use ($user, $gross) {
            $u = User::where('id', $user->id)->lockForUpdate()->first();
            $u->amount_available += $gross;
            $u->save();
        });

        $withdraw->update([
            'status' => 'failed',
            'meta'   => array_merge($withdraw->meta ?? [], ['error' => $error]),
        ]);

        Log::warning('💸 Reembolso realizado após falha no saque Lumnis', [
            'user_id' => $user->id,
            'withdraw_id' => $withdraw->id,
            'reason' => $error,
        ]);
    }
}
