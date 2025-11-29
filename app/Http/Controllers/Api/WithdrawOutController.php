<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Withdraw;
use App\Services\Pluggou\PluggouWithdrawService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Schema;

class WithdrawOutController extends Controller
{
    public function __construct(
        private readonly PluggouWithdrawService $pluggou
    ) {}

    public function store(Request $request)
    {
        /* -------------------------------------------
         * 🔐 Autenticação via Headers
         * ------------------------------------------- */
        $authKey   = $request->header('X-Auth-Key');
        $secretKey = $request->header('X-Secret-Key');
        $idempKey  = $request->header('Idempotency-Key');

        if (!$authKey || !$secretKey) {
            return response()->json([
                'success' => false,
                'error'   => 'Headers de autenticação ausentes.',
                'expect'  => ['X-Auth-Key', 'X-Secret-Key'],
            ], 401);
        }

        /** @var User|null $user */
        $user = User::where('authkey', $authKey)
            ->where('secretkey', $secretKey)
            ->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'error'   => 'Credenciais inválidas.',
            ], 401);
        }

        /* -------------------------------------------
         * 🧾 Validação dos parâmetros
         * ------------------------------------------- */
        $data = $request->validate([
            'amount'      => ['required', 'numeric', 'min:0.01'], // BRUTO
            'pixkey'      => ['required', 'string'],
            'pixkey_type' => ['required', Rule::in(['cpf', 'cnpj', 'email', 'phone', 'random'])],
        ]);

        /* -------------------------------------------
         * ⚙️ Verificação de permissão de saque
         * ------------------------------------------- */
        if (!$user->tax_out_enabled) {
            return response()->json([
                'success' => false,
                'error'   => 'Cashout desabilitado para este usuário.',
                'code'    => 'CASHOUT_DISABLED',
            ], 403);
        }

        /* -------------------------------------------
         * 🧮 Cálculo de taxas
         * ------------------------------------------- */
        $gross = (float) $data['amount'];
        $fee   = 0;
        $mode  = strtolower($user->tax_out_mode);

        $fixed   = (float) ($user->tax_out_fixed ?? 0);
        $percent = (float) ($user->tax_out_percent ?? 0);

        switch ($mode) {
            case 'fixed':
                $fee = $fixed;
                break;

            case 'percent':
                $fee = $gross * ($percent / 100);
                break;

            case 'both':
            case 'mixed':
            case 'fixed_percent':
                $fee = $fixed + ($gross * ($percent / 100));
                break;

            default:
                $fee = 0;
        }

        $fee = round($fee, 2);
        $net = round($gross - $fee, 2);

        if ($net <= 0) {
            return response()->json([
                'success' => false,
                'error'   => 'Valor líquido resultante deve ser maior que zero.',
            ], 422);
        }

        /* -------------------------------------------
         * 🔖 externalId único
         * ------------------------------------------- */
        $externalId = 'withdraw_' . random_int(1000000, 9999999);

        try {
            /* -------------------------------------------
             * (1) Débito + criação do registro
             * ------------------------------------------- */
            $result = DB::transaction(function () use ($user, $gross, $net, $fee, $data, $idempKey, $externalId, $request) {

                $u = User::where('id', $user->id)->lockForUpdate()->first();
                $available = (float) $u->amount_available;

                if ($available < $gross) {
                    return ['error' => [
                        'success'   => false,
                        'error'     => 'Saldo insuficiente.',
                        'available' => $available,
                        'required'  => $gross,
                        'code'      => 'INSUFFICIENT_FUNDS',
                    ]];
                }

                // Debita o BRUTO
                $u->amount_available = round($available - $gross, 2);
                $u->save();

                $payload = [
                    'user_id'         => $u->id,
                    'amount'          => $net,
                    'gross_amount'    => $gross,
                    'fee_amount'      => $fee,
                    'pixkey'          => $data['pixkey'],
                    'pixkey_type'     => $data['pixkey_type'],
                    'status'          => 'pending',
                    'provider'        => 'pluggou',
                    'idempotency_key' => $idempKey ?: $externalId,
                ];

                if (Schema::hasColumn('withdraws', 'meta')) {
                    $payload['meta'] = [
                        'requested_gross' => $gross,
                        'tax_mode'        => $u->tax_out_mode,
                        'tax_fixed'       => $u->tax_out_fixed,
                        'tax_percent'     => $u->tax_out_percent,
                        'api_request'     => true,
                        'external_id'     => $externalId,
                    ];
                }

                $withdraw = Withdraw::create($payload);

                return ['withdraw' => $withdraw, 'user' => $u];
            });

            if (isset($result['error'])) {
                return response()->json($result['error'], 422);
            }

            /** @var Withdraw $withdraw */
            $withdraw = $result['withdraw'];

            /* -------------------------------------------
             * (2) Chamada Pluggou: cria o saque
             * ------------------------------------------- */
            $pixKey = preg_replace('/\D+/', '', $withdraw->pixkey);

            $resp = $this->pluggou->createWithdrawal([
                'amount'    => (int) round($net * 100), // em centavos
                'key_type'  => strtolower($withdraw->pixkey_type),
                'key_value' => $pixKey,
            ], $idempKey);

            /* -------------------------------------------
             * (3) Atualiza a transação
             * ------------------------------------------- */
            DB::transaction(function () use ($withdraw, $resp, $externalId) {

                $data = $resp['data']['data'] ?? null;

                if ($data) {
                    if (Schema::hasColumn('withdraws', 'provider_reference')) {
                        $withdraw->provider_reference = $data['id'] ?? null;
                    }

                    $withdraw->status = $data['status'] ?? 'pending';

                    if (Schema::hasColumn('withdraws', 'provider_message')) {
                        $withdraw->provider_message = $resp['data']['message'] ?? null;
                    }

                    if (Schema::hasColumn('withdraws', 'meta')) {
                        $meta = (array) $withdraw->meta;
                        $meta['provider_echo'] = $resp;
                        $withdraw->meta = $meta;
                    }
                }

                $withdraw->save();
            });

            /* -------------------------------------------
             * (4) Retorno final para API
             * ------------------------------------------- */
            return response()->json([
                'success' => true,
                'message' => 'Saque solicitado com sucesso! Aguardando aprovação.',
                'data' => [
                    'id'            => $withdraw->id,
                    'amount'        => $gross,
                    'liquid_amount' => $net,
                    'pix_key_type'  => $withdraw->pixkey_type,
                    'pix_key'       => $withdraw->pixkey,
                    'status'        => $withdraw->status,
                    'created_at'    => $withdraw->created_at->format('Y-m-d H:i:s'),
                ]
            ]);

        } catch (\Throwable $e) {

            Log::error('Erro ao criar saque', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);

            // reverte saldo
            DB::transaction(function () use ($user, $gross) {
                $u = User::where('id', $user->id)->lockForUpdate()->first();
                $u->amount_available += $gross;
                $u->save();
            });

            if (isset($withdraw)) {
                $withdraw->status = 'failed';
                $withdraw->save();
            }

            return response()->json([
                'success' => false,
                'error'   => 'Falha ao criar saque no provedor.',
            ], 502);
        }
    }
}
