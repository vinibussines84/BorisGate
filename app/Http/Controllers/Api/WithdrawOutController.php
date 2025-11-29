<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Withdraw;
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
        ]);

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

        // 🔖 Referência interna local
        $internalRef = 'withdraw_' . now()->timestamp . '_' . random_int(1000, 9999);

        try {
            // 1️⃣ Criação local e débito do saldo
            $result = DB::transaction(function () use ($user, $gross, $net, $fee, $data, $internalRef) {
                $u = User::where('id', $user->id)->lockForUpdate()->first();

                if ($u->amount_available < $gross) {
                    return ['error' => [
                        'success'   => false,
                        'error'     => 'Saldo insuficiente.',
                        'available' => $u->amount_available,
                        'required'  => $gross,
                    ]];
                }

                $u->amount_available = round($u->amount_available - $gross, 2);
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
                    'idempotency_key' => $internalRef,
                ];

                if (Schema::hasColumn('withdraws', 'meta')) {
                    $payload['meta'] = [
                        'internal_reference' => $internalRef,
                        'tax_fixed'          => $u->tax_out_fixed,
                        'tax_percent'        => $u->tax_out_percent,
                    ];
                }

                $withdraw = Withdraw::create($payload);
                return ['withdraw' => $withdraw];
            });

            if (isset($result['error'])) {
                return response()->json($result['error'], 422);
            }

            /** @var Withdraw $withdraw */
            $withdraw = $result['withdraw'];

            // 2️⃣ Payload da Lumnis
            $payload = [
                "amount"       => (int) round($net * 100), // em centavos
                "key"          => $data['key'],
                "key_type"     => strtoupper($data['key_type']),
                "description"  => $data['description'] ?? 'Saque via API',
                "details"      => [
                    "name"     => $data['details']['name'],
                    "document" => $data['details']['document'],
                ],
                // ✅ O SEU SISTEMA DEFINE o postback automaticamente
                "postback"     => route('webhooks.lumnis.withdraw'),
            ];

            // 3️⃣ Chamada para API da Lumnis
            $resp = $this->lumnis->createWithdrawal($payload);

            // 🧩 Verificação de erro Lumnis
            if (!$resp['success']) {
                $body = $resp['data'] ?? [];
                $content = $body['content'] ?? [];
                $errorName = $content['name'] ?? null;

                // ⚠️ Caso seja saldo insuficiente no provedor
                if ($errorName === 'INSUFFICIENT_FUNDS') {
                    DB::transaction(function () use ($user, $gross) {
                        $u = User::where('id', $user->id)->lockForUpdate()->first();
                        $u->amount_available += $gross;
                        $u->save();
                    });

                    $withdraw->update(['status' => 'failed']);

                    return response()->json([
                        'success' => false,
                        'error'   => 'Tente novamente em 5 minutos ou contate o suporte.',
                    ], 422);
                }

                $msg = is_array($resp['message'])
                    ? implode('; ', $resp['message'])
                    : ($resp['message'] ?? 'Erro Lumnis Cashout');
                throw new \Exception($msg);
            }

            // ✅ Sucesso
            $batch = $resp['data']['data'][0] ?? null;
            $identifier = $batch['identifier'] ?? null;
            $status = strtolower($batch['status'] ?? 'pending');

            DB::transaction(function () use ($withdraw, $identifier, $status, $resp) {
                $withdraw->status = $status;
                $withdraw->provider_reference = $identifier;
                if (Schema::hasColumn('withdraws', 'meta')) {
                    $meta = (array) $withdraw->meta;
                    $meta['lumnis_response'] = $resp;
                    $withdraw->meta = $meta;
                }
                $withdraw->save();
            });

            // 🚀 DISPARA WEBHOOK PARA O CLIENTE (withdraw.created)
            if ($user->webhook_enabled && $user->webhook_out_url) {
                try {
                    Http::timeout(10)->post($user->webhook_out_url, [
                        'event' => 'withdraw.created',
                        'data'  => [
                            'id'            => $withdraw->id,
                            'amount'        => $gross,
                            'liquid_amount' => $net,
                            'pix_key'       => $withdraw->pixkey,
                            'pix_key_type'  => $withdraw->pixkey_type,
                            'status'        => $status,
                            'reference'     => $identifier,
                        ],
                    ]);
                } catch (\Throwable $ex) {
                    Log::warning('⚠️ Falha ao enviar webhook de criação de saque', [
                        'user_id' => $user->id,
                        'url'     => $user->webhook_out_url,
                        'error'   => $ex->getMessage(),
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Saque solicitado com sucesso!',
                'data' => [
                    'id'            => $withdraw->id,
                    'amount'        => $gross,
                    'liquid_amount' => $net,
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

            DB::transaction(function () use ($user, $gross) {
                $u = User::where('id', $user->id)->lockForUpdate()->first();
                $u->amount_available += $gross;
                $u->save();
            });

            if (isset($withdraw)) {
                $withdraw->update(['status' => 'failed']);
            }

            return response()->json([
                'success' => false,
                'error'   => 'Falha ao criar saque. ' . $e->getMessage(),
            ], 502);
        }
    }
}
