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
        // 🔐 Autenticação via Headers
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

        // 🧾 Validação dos campos recebidos
        $data = $request->validate([
            'amount'            => ['required', 'numeric', 'min:0.01'],
            'key'               => ['required', 'string'],
            'key_type'          => ['required', Rule::in(['EVP', 'EMAIL', 'PHONE', 'CPF', 'CNPJ'])],
            'description'       => ['nullable', 'string', 'max:255'],
            'details.name'      => ['required', 'string', 'max:100'],
            'details.document'  => ['required', 'string', 'max:20'],
            'external_id'       => ['sometimes', 'string', 'max:64'],
        ]);

        // 🔎 Validação REAL da chave Pix
        if (!KeyValidator::validate($data['key'], $data['key_type'])) {
            return response()->json([
                'success' => false,
                'error'   => 'Chave Pix inválida para o tipo informado.'
            ], 422);
        }

        // ⚙️ Verificação de permissão de saque
        if (!$user->tax_out_enabled) {
            return response()->json([
                'success' => false,
                'error'   => 'Cashout desabilitado para este usuário.',
            ], 403);
        }

        // 💰 Cálculo de taxas
        $gross = (float) $data['amount'];
        $fixed = (float) ($user->tax_out_fixed ?? 0);
        $percent = (float) ($user->tax_out_percent ?? 0);
        $fee = round($fixed + ($gross * $percent / 100), 2);
        $net = round($gross - $fee, 2);

        if ($net <= 0) {
            return response()->json(['success' => false, 'error' => 'Valor líquido inválido.'], 422);
        }

        // 🧠 Define external_id único
        $externalId = $data['external_id'] ?? 'WD_' . now()->timestamp . '_' . random_int(1000, 9999);

        // 🚫 Previne duplicidade
        if (Withdraw::where('user_id', $user->id)->where('external_id', $externalId)->exists()) {
            return response()->json([
                'success' => false,
                'error'   => 'Duplicate external_id. Please provide a unique value.',
            ], 409);
        }

        // 🔖 Referência interna
        $internalRef = 'withdraw_' . now()->timestamp . '_' . random_int(1000, 9999);

        try {
            // 1️⃣ Criação local e débito
            $result = DB::transaction(function () use ($user, $gross, $net, $fee, $data, $internalRef, $externalId) {
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

            // 2️⃣ Payload Lumnis
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

            // 3️⃣ Chamada da API
            $resp = $this->lumnis->createWithdrawal($payload);

            // ❌ Falha → estorna saldo e retorna erro
            if (!$resp['success'] || empty($resp['data']['data'][0])) {

                $this->refund($user, $gross, $withdraw, $resp);

                return response()->json([
                    'success' => false,
                    'error'   => 'Falha ao criar saque na Lumnis.',
                    'details' => $resp
                ], 502);
            }

            // ✅ Sucesso
            $batch = $resp['data']['data'][0];
            $identifier = $batch['identifier'] ?? null;
            $status = strtolower($batch['status'] ?? 'pending');

            DB::transaction(function () use ($withdraw, $identifier, $status, $resp) {
                $withdraw->update([
                    'status' => $status,
                    'provider_reference' => $identifier,
                    'meta' => array_merge($withdraw->meta ?? [], [
                        'lumnis_response' => $resp
                    ])
                ]);
            });

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
                    'reference'     => $identifier,
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

    private function refund(User $user, float $gross, Withdraw $withdraw, $error = null)
    {
        DB::transaction(function () use ($user, $gross) {
            $u = User::where('id', $user->id)->lockForUpdate()->first();
            $u->amount_available += $gross;
            $u->save();
        });

        $withdraw->update([
            'status' => 'failed',
            'meta' => array_merge($withdraw->meta ?? [], [
                'error' => $error
            ])
        ]);
    }
}
