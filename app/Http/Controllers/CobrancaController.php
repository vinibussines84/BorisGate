<?php

namespace App\Http\Controllers;

use App\Models\Cobranca;
use App\Models\Transaction;
use App\Enums\TransactionStatus;
use App\Services\GatewayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Inertia\Inertia;

class CobrancaController extends Controller
{
    /** Página Inertia */
    public function index(Request $request)
    {
        return Inertia::render('Cobranca/Index');
    }

    /** GET /api/charges/summary */
    public function summary(Request $request)
    {
        $user = $request->user();

        $pending = Cobranca::where('user_id', $user->id)->where('status', 'pending')->count();
        $paid    = Cobranca::where('user_id', $user->id)->where('status', 'paid')->count();

        return response()->json([
            'success' => true,
            'pending' => (int) $pending,
            'paid'    => (int) $paid,
        ]);
    }

    /** GET /api/charges?status=pending|paid|all */
    public function list(Request $request)
    {
        $user   = $request->user();
        $status = (string) $request->query('status', 'all');

        $q = Cobranca::where('user_id', $user->id)->latest('created_at');
        if ($status !== 'all') {
            $q->where('status', $status);
        }

        // Mantém compatibilidade com seu front: retorna em "data"
        $rows = $q->paginate((int) $request->query('per_page', 15));

        $data = collect($rows->items())->map(function (Cobranca $c) {
            // tenta encontrar a Transaction por external_id para pegar e2e/txid real
            $trx = $c->external_id
                ? Transaction::where('external_id', $c->external_id)->first()
                : null;

            $refTxid = $trx?->e2e_id ?: $trx?->txid ?: $c->provider_transaction_id;

            return [
                'id'          => $c->id,
                'amount'      => (float) $c->amount,
                'status'      => $c->status, // webhook já sincroniza para 'paid'
                'external_id' => $c->external_id,
                'txid'        => $refTxid,   // usado como "Ref." na tabela
                'qrcode'      => $c->qrcode,
                'paid_at'     => optional($c->paid_at)->toIso8601String(),
                'created_at'  => optional($c->created_at)->toIso8601String(),
            ];
        });

        return response()->json([
            'success' => true,
            'data'    => $data,
            'meta'    => [
                'current_page' => $rows->currentPage(),
                'last_page'    => $rows->lastPage(),
                'per_page'     => $rows->perPage(),
                'total'        => $rows->total(),
            ],
        ]);
    }

    /**
     * POST /cobranca
     * amount + acceptTerms [+ payer] [+ external_id]
     */
    public function store(Request $request, GatewayService $gateway)
    {
        $v = Validator::make($request->all(), [
            'amount'         => ['required', 'numeric', 'min:1', 'max:1000000'],
            'acceptTerms'    => ['accepted'],
            'external_id'    => ['nullable', 'string', 'max:100'],
            'payer.name'     => ['nullable', 'string', 'max:140'],
            'payer.email'    => ['nullable', 'email', 'max:140'],
            'payer.document' => ['nullable', 'string', 'max:32'],
            'payer.phone'    => ['nullable', 'string', 'max:20'],
        ], [
            'acceptTerms.accepted' => 'Você precisa aceitar os termos para continuar.',
        ]);

        if ($v->fails()) {
            return response()->json(['success' => false, 'errors' => $v->errors()], 422);
        }

        $data = $v->validated();
        $user = $request->user();

        try {
            $result = DB::transaction(function () use ($user, $data, $gateway) {

                $externalId = $data['external_id'] ?? ('COB-' . Str::orderedUuid());

                // Evita duplicar cobrança por external_id
                $existing = Cobranca::where('user_id', $user->id)
                    ->where('external_id', $externalId)
                    ->first();

                if ($existing) {
                    // Garante Transaction vinculada/atualizada
                    $this->ensureTransactionForCobranca($existing, $user, $existing->payload ?? []);
                    return ['cobranca' => $existing->fresh(), 'just_created' => false];
                }

                $payer = array_filter([
                    'name'     => data_get($data, 'payer.name', $user->name ?? null),
                    'email'    => data_get($data, 'payer.email', $user->email ?? null),
                    'document' => data_get($data, 'payer.document', $user->document ?? null),
                    'phone'    => data_get($data, 'payer.phone', $user->phone ?? null),
                ], fn ($v) => !is_null($v) && $v !== '');

                // Cria depósito no provedor
                $resp = $gateway->createDeposit([
                    'amount'      => (float) $data['amount'],
                    'external_id' => $externalId,
                    'payer'       => $payer,
                ]);

                // Normalizações de provider
                $provider     = 'gateway';
                $providerTxId = data_get($resp, 'qrCodeResponse.transactionId')
                              ?? data_get($resp, 'transaction_id')
                              ?? data_get($resp, 'transactionId')
                              ?? data_get($resp, 'id');

                $statusRaw    = strtolower((string) (data_get($resp, 'qrCodeResponse.status')
                              ?? data_get($resp, 'status', 'PENDING')));

                $status       = match ($statusRaw) {
                    'completed', 'paid', 'success' => 'paid',
                    'failed', 'error'               => 'failed',
                    'refunded'                      => 'refunded',
                    default                         => 'pending',
                };

                $qrcode   = data_get($resp, 'qrCodeResponse.qrcode')
                          ?? data_get($resp, 'qrcode')
                          ?? data_get($resp, 'qr_code')
                          ?? data_get($resp, 'emv');

                $respTxid = data_get($resp, 'qrCodeResponse.txid')
                          ?? data_get($resp, 'txid')
                          ?? null;

                if (!$qrcode) {
                    throw new \RuntimeException('Gateway não retornou qrcode válido.');
                }

                // Cria a cobrança local
                $cobranca = Cobranca::create([
                    'user_id'                 => $user->id,
                    'amount'                  => (float) $data['amount'],
                    'status'                  => $status,
                    'external_id'             => $externalId,
                    'provider'                => $provider,
                    'provider_transaction_id' => $providerTxId,
                    'qrcode'                  => $qrcode,
                    'payload'                 => $resp,
                    'payer'                   => $payer ?: null,
                    'paid_at'                 => null,
                ]);

                // Cria/atualiza a Transaction de entrada vinculada
                $this->ensureTransactionForCobranca($cobranca, $user, $resp, $respTxid);

                return ['cobranca' => $cobranca->fresh(), 'just_created' => true];
            });

            return response()->json([
                'success'  => true,
                'created'  => $result['just_created'],
                'cobranca' => [
                    'id'          => $result['cobranca']->id,
                    'amount'      => (float) $result['cobranca']->amount,
                    'status'      => $result['cobranca']->status,
                    'external_id' => $result['cobranca']->external_id,
                    'txid'        => $result['cobranca']->provider_transaction_id, // referência
                    'qrcode'      => $result['cobranca']->qrcode,
                    'paid_at'     => optional($result['cobranca']->paid_at)->toIso8601String(),
                    'created_at'  => optional($result['cobranca']->created_at)->toIso8601String(),
                ],
            ], $result['just_created'] ? 201 : 200);

        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Não foi possível criar a cobrança.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /** GET /api/charges/{cobranca} */
    public function show(Request $request, Cobranca $cobranca)
    {
        $user = $request->user();
        if ((int) $cobranca->user_id !== (int) $user->id) {
            return response()->json(['success' => false, 'message' => 'Não autorizado.'], 403);
        }

        // busca E2E/txid real na Transaction (se existir)
        $trx = $cobranca->external_id
            ? Transaction::where('external_id', $cobranca->external_id)->first()
            : null;
        $refTxid = $trx?->e2e_id ?: $trx?->txid ?: $cobranca->provider_transaction_id;

        return response()->json([
            'success'  => true,
            'cobranca' => [
                'id'          => $cobranca->id,
                'amount'      => (float) $cobranca->amount,
                'status'      => $cobranca->status,
                'external_id' => $cobranca->external_id,
                'txid'        => $refTxid,
                'qrcode'      => $cobranca->qrcode,
                'paid_at'     => optional($cobranca->paid_at)->toIso8601String(),
                'created_at'  => optional($cobranca->created_at)->toIso8601String(),
            ],
        ]);
    }

    // ================= helpers =================

    /**
     * Garante que exista uma Transaction vinculada a esta cobrança.
     * Usa external_id como chave natural e marca external_reference = "cobranca:{id}".
     * Tenta preencher txid inicial (se enviado pelo provedor).
     */
    protected function ensureTransactionForCobranca(Cobranca $cobranca, $user, array $providerPayload = [], ?string $txid = null): void
    {
        $externalId   = $cobranca->external_id;
        $providerTxId = $cobranca->provider_transaction_id;

        // atributos base
        $attrs = [
            'tenant_id'                => $user->tenant_id ?? null,
            'user_id'                  => $user->id,
            'amount'                   => (float) $cobranca->amount,
            'fee'                      => 0,
            'net_amount'               => (float) $cobranca->amount, // saldo líquido é tratado depois; aqui não bloqueia
            'direction'                => Transaction::DIR_IN,
            'status'                   => TransactionStatus::PENDENTE, // webhook muda para PAGA
            'currency'                 => 'BRL',
            'method'                   => 'pix',
            'provider'                 => $cobranca->provider ?? 'gateway',
            'provider_transaction_id'  => $providerTxId,
            'txid'                     => $txid ?: null, // pode vir vazio e depois será preenchido pelo webhook
            'external_reference'       => 'cobranca:' . $cobranca->id,
            'external_id'              => $externalId,
            'description'              => 'Depósito Pix (cobrança)',
            'provider_payload'         => $providerPayload,
        ];

        // 1) tenta localizar por external_id (mais confiável)
        $trx = $externalId
            ? Transaction::where('external_id', $externalId)->first()
            : null;

        // 2) fallback por external_reference (caso tenha sido criado antes sem external_id)
        if (!$trx) {
            $trx = Transaction::where('external_reference', 'cobranca:' . $cobranca->id)->first();
        }

        if ($trx) {
            // Mescla payload mantendo histórico
            $payload = $this->mergePayload((array) ($trx->provider_payload ?? []), $providerPayload);
            $update  = array_filter(array_replace($attrs, ['provider_payload' => $payload]), fn ($v) => !is_null($v));

            // Não sobrescreva e2e_id/txid se já houver um preenchido
            if (!empty($trx->txid) && array_key_exists('txid', $update)) {
                unset($update['txid']);
            }
            $trx->fill($update)->save();
            return;
        }

        // Novo registro
        $attrs['provider_payload'] = $this->mergePayload([], $providerPayload);
        Transaction::create($attrs);
    }

    /** mantém histórico simples no provider_payload */
    protected function mergePayload(array $old, array $incoming): array
    {
        $hist = $old['_history'] ?? [];
        if (!empty($incoming)) {
            $hist[] = ['at' => now()->toIso8601String(), 'data' => $incoming];
        }
        $merged = array_replace_recursive($old, $incoming);
        $merged['_history'] = $hist;
        return $merged;
    }
}
