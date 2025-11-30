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
use Illuminate\Support\Facades\Schema;

class WithdrawOutController extends Controller
{
    public function __construct(
        private readonly LumnisCashoutService $lumnis
    ) {}

    public function store(Request $request)
    {
        /** ---------------------------------------------------------
         *  🔐 Autenticação via headers
         * --------------------------------------------------------- */
        $authKey   = $request->header('X-Auth-Key');
        $secretKey = $request->header('X-Secret-Key');

        if (!$authKey || !$secretKey) {
            return response()->json([
                'success' => false,
                'error'   => 'Headers ausentes. Envie X-Auth-Key e X-Secret-Key.',
            ], 401);
        }

        $user = User::where('authkey', $authKey)
            ->where('secretkey', $secretKey)
            ->first();

        if (!$user) {
            return response()->json(['success' => false, 'error' => 'Credenciais inválidas.'], 401);
        }

        /** ---------------------------------------------------------
         *  🧾 Validação
         * --------------------------------------------------------- */
        $data = $request->validate([
            'amount'            => ['required', 'numeric', 'min:0.01'],
            'key'               => ['required', 'string'],
            'key_type'          => ['required', Rule::in(['EVP', 'EMAIL', 'PHONE', 'CPF', 'CNPJ'])],
            'description'       => ['nullable', 'string', 'max:255'],
            'details.name'      => ['required', 'string', 'max:100'],
            'details.document'  => ['required', 'string', 'max:20'],
            'external_id'       => ['sometimes', 'string', 'max:64'],
        ]);

        /** ---------------------------------------------------------
         *  🔎 Validação da chave PIX
         * --------------------------------------------------------- */
        if (!KeyValidator::validate($data['key'], $data['key_type'])) {
            return response()->json([
                'success' => false,
                'error'   => 'Chave Pix inválida para o tipo informado.'
            ], 422);
        }

        /** ---------------------------------------------------------
         *  ⚙ Permissão
         * --------------------------------------------------------- */
        if (!$user->tax_out_enabled) {
            return response()->json([
                'success' => false,
                'error'   => 'Cashout desabilitado para este usuário.',
            ], 403);
        }

        /** ---------------------------------------------------------
         *  💰 Cálculo de taxas
         * --------------------------------------------------------- */
        $gross = (float) $data['amount'];
        $fixed = (float) ($user->tax_out_fixed ?? 0);
        $percent = (float) ($user->tax_out_percent ?? 0);
        $fee = round($fixed + ($gross * $percent / 100), 2);
        $net = round($gross - $fee, 2);

        if ($net <= 0) {
            return response()->json(['success' => false, 'error' => 'Valor líquido inválido.'], 422);
        }

        /** ---------------------------------------------------------
         *  🔢 External ID
         * --------------------------------------------------------- */
        $externalId = $data['external_id'] ??
            'WD_' . now()->timestamp . '_' . random_int(1000, 9999);

        if (Withdraw::where('user_id', $user->id)->where('external_id', $externalId)->exists()) {
            return response()->json([
                'success' => false,
                'error'   => 'Duplicate external_id. Forneça um valor único.',
            ], 409);
        }

        $internalRef = 'withdraw_' . now()->timestamp . '_' . random_int(1000, 9999);

        /** ---------------------------------------------------------
         *  🧾 1️⃣ Criação local + débito em transação
         * --------------------------------------------------------- */
        try {

            $result = DB::transaction(function () use ($user, $gross, $net, $fee, $data, $externalId, $internalRef) {

                $u = User::where('id', $user->id)->lockForUpdate()->first();

                if ($u->amount_available < $gross) {
                    return ['error' => [
                        'success'   => false,
                        'error'     => 'Saldo insuficiente.',
                        'available' => $u->amount_available,
                        'required'  => $gross,
                    ]];
                }

                $u->amount_available -= $gross;
                $u->save();

                $payload = [
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
                ];

                if (Schema::hasColumn('withdraws', 'meta')) {
                    $payload['meta'] = [
                        'internal_reference' => $internalRef,
                        'tax_fixed'          => $u->tax_out_fixed,
                        'tax_percent'        => $u->tax_out_percent,
                    ];
                }

                return ['withdraw' => Withdraw::create($payload)];
            });

            if (isset($result['error'])) {
                return response()->json($result['error'], 422);
            }

            $withdraw = $result['withdraw'];

            /** ---------------------------------------------------------
             *  2️⃣ Payload enviado para Lumnis
             * --------------------------------------------------------- */
            $payload = [
                "amount"      => (int) round($net * 100),
                "key"         => $data['key'],
                "key_type"    => strtoupper($data['key_type']),
                "description" => $data['description'] ?? 'Saque via API',
                "details"     => [
                    "name"     => $data['details']['name'],
                    "document" => $data['details']['document'],
                ],
                "postback" => route('webhooks.lumnis.withdraw'),
            ];

            /** ---------------------------------------------------------
             *  3️⃣ Chamada real para Lumnis
             * --------------------------------------------------------- */
            $resp = $this->lumnis->createWithdrawal($payload);

            if (!$resp['success']) {
                $this->refund($user, $gross, $withdraw, $resp);
                return response()->json([
                    'success' => false,
                    'error'   => 'Falha ao criar saque na Lumnis.',
                    'details' => $resp,
                ], 502);
            }

            /** ---------------------------------------------------------
             *  4️⃣ IDENTIFICADOR REAL (receipt.identifier)
             * --------------------------------------------------------- */
            $providerReference =
                data_get($resp, 'data.data.0.receipt.0.identifier') ??
                data_get($resp, 'data.data.0.identifier') ??
                data_get($resp, 'data.data.0.id');

            $status = strtolower(data_get($resp, 'data.data.0.status', 'pending'));

            /** ---------------------------------------------------------
             *  5️⃣ Atualiza o saque com o identifier
             * --------------------------------------------------------- */
            DB::transaction(function () use ($withdraw, $providerReference, $status, $resp) {
                $withdraw->update([
                    'status'             => $status,
                    'provider_reference' => $providerReference,
                    'meta'               => array_merge($withdraw->meta ?? [], [
                        'lumnis_response' => $resp,
                        'provider_raw'    => $resp,
                    ]),
                ]);
            });

            /** ---------------------------------------------------------
             *  6️⃣ Dispara Webhook OUT (criação)
             * --------------------------------------------------------- */
            if ($user->webhook_enabled && $user->webhook_out_url) {
                try {
                    Http::timeout(10)->post($user->webhook_out_url, [
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
                            'endtoend'      => null,
                        ],
                    ]);
                } catch (\Throwable $e) {
                    Log::warning('Falha ao enviar webhook withdraw.created', [
                        'user_id' => $user->id,
                        'error'   => $e->getMessage()
                    ]);
                }
            }

            /** ---------------------------------------------------------
             *  🔚 Retorno oficial
             * --------------------------------------------------------- */
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

            Log::error('Erro ao criar saque via Lumnis', [
                'user_id' => $user->id,
                'message' => $e->getMessage(),
            ]);

            if (isset($withdraw)) {
                $this->refund($user, $gross, $withdraw, $e->getMessage());
            }

            return response()->json([
                'success' => false,
                'error'   => 'Falha ao criar saque. ' . $e->getMessage(),
            ], 502);
        }
    }

    /** ---------------------------------------------------------
     *  🔁 Estorno
     * --------------------------------------------------------- */
    private function refund(User $user, float $gross, Withdraw $withdraw, $error = null)
    {
        DB::transaction(function () use ($user, $gross) {
            $u = User::where('id', $user->id)->lockForUpdate()->first();
            $u->amount_available += $gross;
            $u->save();
        });

        $withdraw->update([
            'status' => 'failed',
            'meta'   => array_merge($withdraw->meta ?? [], [
                'error' => $error
            ]),
        ]);
    }
}
