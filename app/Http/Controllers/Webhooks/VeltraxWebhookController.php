<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

use App\Models\Transaction; // PIX IN (cash-in)
use App\Models\Withdraw;    // PIX OUT (saque)
use App\Enums\TransactionStatus;

class VeltraxWebhookController extends Controller
{
    public function __invoke(Request $request)
    {
        $p = $this->payload($request);

        // Heurística — trate como OUT somente quando ficar claro
        if ($this->looksLikeWithdraw($p)) {
            return $this->handleWithdraw($request, $p);
        }

        return $this->handlePixIn($request, $p);
    }

    private function payload(Request $request): array
    {
        $raw  = $request->getContent();
        $json = json_decode($raw, true);
        return is_array($json) ? $json : $request->all();
    }

    /* =====================================================================
     | Heurística segura OUT (saque) x IN (depósito)
     ===================================================================== */
    private function looksLikeWithdraw(array $p): bool
    {
        $type      = strtolower((string)($p['type'] ?? ''));
        $direction = strtolower((string)($p['direction'] ?? ''));

        if (in_array($type, ['withdraw','payout','cashout'], true)) return true;
        if (in_array($type, ['deposit','pixin','cashin','payment'], true)) return false;

        if ($direction === 'out') return true;
        if ($direction === 'in')  return false;

        $hasPayee = isset($p['payee']) || isset($p['beneficiary']) || isset($p['destinatario'])
                 || isset($p['nome_recebedor']) || isset($p['cpf_recebedor']);
        $hasPayer = isset($p['payer']) || isset($p['pagador']) || isset($p['nome_pagador']) || isset($p['cpf_pagador']);

        if ($hasPayee && !$hasPayer) return true;
        if ($hasPayer && !$hasPayee) return false;

        // Em dúvida, trate como IN para não perder confirmação de entrada
        return false;
    }

    /* ======================== PIX OUT (saque) ======================== */
    private function handleWithdraw(Request $request, array $p)
    {
        $txid      = (string)($p['transaction_id'] ?? $p['transaction'] ?? '');
        $external  = (string)($p['external_id'] ?? $p['reference'] ?? $p['idempotency_key'] ?? '');
        $status    = strtoupper(trim((string)($p['status'] ?? '')));
        $amount    = isset($p['amount']) ? (float)$p['amount'] : null;
        $fee       = isset($p['fee']) ? (float)$p['fee'] : null;

        $e2e  = (string)($p['end_to_end'] ?? $p['e2e'] ?? $p['endToEndId'] ?? '');
        $ispb = (string)($p['ispb'] ?? '');
        $nome = (string)($p['payee']['name'] ?? $p['nome_recebedor'] ?? '');
        $docM = (string)($p['payee']['document'] ?? $p['cpf_recebedor'] ?? '');

        Log::info('Veltrax WITHDRAW webhook', [
            'ip'      => $request->ip(),
            'summary' => ['txid' => $txid, 'external' => $external, 'status' => $status, 'amount' => $amount, 'e2e' => $e2e],
            'raw'     => $p,
        ]);

        // ===== Localiza withdraw por ordem de confiabilidade =====
        $w = null;

        if ($txid !== '' && Schema::hasColumn('withdraws', 'provider_reference')) {
            $w = Withdraw::where('provider_reference', $txid)->latest('id')->first();
        }

        if (!$w && $txid !== '' && $this->hasMeta('withdraws')) {
            $w = Withdraw::where('meta->provider_txid', $txid)->latest('id')->first();
        }

        if (!$w && $e2e !== '' && $this->hasMeta('withdraws')) {
            $w = Withdraw::where('meta->end_to_end', $e2e)->latest('id')->first();
        }

        if (!$w && $external !== '' && Schema::hasColumn('withdraws', 'idempotency_key')) {
            $w = Withdraw::where('idempotency_key', $external)->latest('id')->first();
        }

        if (!$w && !is_null($amount)) {
            $w = Withdraw::query()
                ->whereIn('status', ['pending', 'processing'])
                ->when(Schema::hasColumn('withdraws', 'provider'), fn ($q) => $q->where('provider', 'veltraxpay'))
                ->whereBetween('amount', [max(0, $amount - 0.10), $amount + 0.10])
                ->where('created_at', '>=', now()->subHours(72))
                ->latest('id')
                ->first();
        }

        if (!$w) {
            Log::warning('Veltrax webhook: withdraw não encontrado', [
                'txid' => $txid ?: null,
                'e2e'  => $e2e ?: null,
                'ext'  => $external ?: null,
                'amt'  => $amount,
            ]);
            return response()->json(['accepted' => true], 202);
        }

        DB::transaction(function () use ($w, $txid, $external, $e2e, $fee, $ispb, $nome, $docM, $status, $p, $amount) {
            // Atualiza META
            if ($this->hasMeta('withdraws')) {
                $meta = (array)($w->meta ?? []);
                if ($txid     !== '') $meta['provider_txid'] = $txid;
                if ($e2e      !== '') $meta['end_to_end']    = $e2e;
                if ($external !== '') $meta['external_id']   = $external;
                if (!is_null($fee))   $meta['provider_fee']  = (float)$fee;
                if (!is_null($amount)) $meta['provider_amount'] = (float)$amount;
                if ($ispb     !== '') $meta['ispb']          = $ispb;
                if ($nome     !== '') $meta['payee_name']    = $nome;
                if ($docM     !== '') $meta['payee_document_mask'] = $docM;
                $meta['provider_status'] = $status;
                $meta['provider_raw']    = $p;
                $w->meta = $meta;
            }

            if (Schema::hasColumn('withdraws', 'provider_reference') && empty($w->provider_reference) && $txid !== '') {
                $w->provider_reference = $txid;
            }

            if (!is_null($fee)) {
                if (Schema::hasColumn('withdraws', 'fee_amount') && (is_null($w->fee_amount) || $w->fee_amount == 0)) {
                    $w->fee_amount = round((float)$fee, 2);
                }
                if (!is_null($amount) && Schema::hasColumn('withdraws', 'gross_amount') && is_null($w->gross_amount)) {
                    $w->gross_amount = round(((float)($amount)) , 2);
                }
            }

            $mapped = match ($status) {
                'COMPLETED','PAID','APPROVED','CONFIRMED'      => 'paid',
                'FAILED','CANCELLED','CANCELED','DENIED'       => 'failed',
                'PROCESSING','PENDING','AUTHORIZED','CREATED'  => 'processing',
                default                                         => 'processing',
            };

            if (in_array($w->status, ['paid','canceled','failed'], true)) {
                if ($mapped === 'paid' && $this->hasColumn($w->getTable(), 'processed_at') && is_null($w->processed_at)) {
                    $w->processed_at = now();
                    $w->save();
                } else {
                    $w->save();
                }
                // 🔔 Só dispara se estiver pago
                if ($w->status === 'paid' || $mapped === 'paid') {
                    $this->notifyUserWebhookOut($w);
                }
                return;
            }

            $w->status = $mapped;

            if (in_array($mapped, ['paid','failed','canceled'], true) && $this->hasColumn($w->getTable(), 'processed_at')) {
                $w->processed_at = $w->processed_at ?? now();
            }

            $w->save();

            // 🔔 ENVIA APENAS SE PAGO
            if ($w->status === 'paid') {
                $this->notifyUserWebhookOut($w);
            }
        });

        return response()->json(['ok' => true], 200);
    }

    /* ======================== PIX IN (cash-in) ======================== */
    private function handlePixIn(Request $request, array $p)
    {
        $externalId = (string)($p['external_id'] ?? $p['reference'] ?? '');
        $txid       = (string)($p['transaction_id'] ?? $p['txid'] ?? '');
        $statusProv = strtoupper(trim((string)($p['status'] ?? '')));
        $e2e        = (string)($p['end_to_end'] ?? $p['e2e'] ?? $p['endToEndId'] ?? '');
        $amount     = isset($p['amount']) ? (float)$p['amount'] : null;

        $ispb = (string)($p['ispb'] ?? '');
        $nome = (string)($p['payer']['name'] ?? $p['nome_pagador'] ?? '');
        $docM = (string)($p['payer']['document'] ?? $p['cpf_pagador'] ?? '');

        Log::info('Veltrax PIX IN webhook', [
            'ip' => $request->ip(),
            'summary' => [
                'externalId'    => $externalId,
                'transactionId' => $txid,
                'status'        => $statusProv,
                'amount'        => $amount,
                'e2e'           => $e2e,
            ],
            'raw' => $p,
        ]);

        // === Finder robusto (sem filtrar por direction) ===
        $t = null;

        if ($externalId !== '') {
            $t = Transaction::where('external_reference', $externalId)->latest('id')->first();
        }
        if (!$t && $txid !== '') {
            $t = Transaction::where('txid', $txid)->latest('id')->first();
        }
        if (!$t && $e2e !== '') {
            $t = Transaction::where('e2e_id', $e2e)->latest('id')->first();
        }
        if (!$t && !is_null($amount)) {
            $t = Transaction::query()
                ->whereIn('status', [
                    TransactionStatus::PENDENTE->value,
                    'pendente','pending','processing',
                ])
                ->whereBetween('amount', [max(0, $amount - 0.10), $amount + 0.10])
                ->where('created_at', '>=', now()->subHours(48))
                ->latest('id')
                ->first();
        }

        if (!$t) {
            Log::warning('Transação PIX IN não encontrada', [
                'external_id'    => $externalId ?: null,
                'transaction_id' => $txid ?: null,
                'end_to_end'     => $e2e ?: null,
                'amount'         => $amount,
            ]);
            return response()->json(['accepted' => true], 202);
        }

        DB::transaction(function () use ($t, $txid, $e2e, $ispb, $nome, $docM, $statusProv, $p) {
            // Preenche identificadores se faltando
            if ($txid !== '' && empty($t->txid))   $t->txid   = $txid;
            if ($e2e  !== '' && empty($t->e2e_id)) $t->e2e_id = $e2e;

            // provider_payload JSON
            $payload = (array)($t->provider_payload ?? []);
            $payload['provider_status'] = $statusProv;
            $payload['provider_raw']    = $p;
            if ($e2e  !== '') $payload['end_to_end'] = $e2e;
            if ($ispb !== '') $payload['ispb']      = $ispb;
            if ($nome !== '') $payload['nome_pagador'] = $nome;
            if ($docM !== '') $payload['cpf_pagador']  = $docM;
            $t->provider_payload = $payload;

            $normalized = match ($statusProv) {
                'PAID','CONFIRMED','COMPLETED','SETTLED'        => 'paid',
                'FAILED','CANCELLED','CANCELED','DENIED'        => 'failed',
                'PROCESSING','PENDING','AUTHORIZED','CREATED'   => 'pending',
                default                                         => 'pending',
            };

            $enum = TransactionStatus::fromLoose($normalized);

            $current = $this->enumValue($t->status);
            $alreadyFinal = in_array($current, [
                TransactionStatus::PAGA->value,
                TransactionStatus::FALHA->value,
                TransactionStatus::ERRO->value,
            ], true);

            if ($alreadyFinal) {
                $t->save();
                Log::info('PIX IN já final; mantido', [
                    'id' => $t->id, 'status' => $current,
                    'prov'=>$statusProv, 'normalized'=>$normalized, 'enum'=>$enum->value
                ]);
                // 🔔 Só envia se já estava PAGA
                if ($current === TransactionStatus::PAGA->value) {
                    $this->notifyUserWebhookIn($t);
                }
                return;
            }

            $old = $current;

            $t->status = $enum;

            if ($enum === TransactionStatus::PAGA) {
                // garante e2e salvo quando pago
                if (empty($t->e2e_id) && !empty($payload['end_to_end'])) {
                    $t->e2e_id = (string)$payload['end_to_end'];
                }
                if (Schema::hasColumn($t->getTable(), 'paid_at')) {
                    $t->paid_at = $t->paid_at ?? now();
                }
            } elseif (in_array($enum, [TransactionStatus::FALHA, TransactionStatus::ERRO], true)) {
                if (Schema::hasColumn($t->getTable(), 'canceled_at')) {
                    $t->canceled_at = $t->canceled_at ?? now();
                }
            }

            $t->save();
            $t->refresh();

            Log::info('PIX IN status atualizado', [
                'id'   => $t->id,
                'from' => $old,
                'to'   => $this->enumValue($t->status),
                'prov'=>$statusProv, 'normalized'=>$normalized, 'enum'=>$enum->value
            ]);

            // 🔔 ENVIA APENAS SE PAGO
            if ($enum === TransactionStatus::PAGA) {
                $this->notifyUserWebhookIn($t);
            }
        });

        return response()->json(['ok' => true], 200);
    }

    /* ======================== Webhook -> Cliente (IN/OUT) ======================== */

    /** Dispara webhook para o cliente quando a transação PIX IN muda (apenas pago). */
    private function notifyUserWebhookIn(Transaction $t): void
    {
        try {
            $user = $t->user ?? null;
            if (!$user) {
                Log::warning('Webhook IN: transação sem usuário', ['transaction_id' => $t->id]);
                return;
            }

            $enabled = (bool)($user->webhook_enabled ?? false);
            $url     = (string)($user->webhook_in_url ?? '');

            if (!$enabled || empty($url)) {
                Log::info('Webhook IN desabilitado/sem URL', ['user_id' => $user->id ?? null, 'transaction_id' => $t->id]);
                return;
            }

            $payload = $this->makePixPayload('pix.transaction.update', 'in', $t, [
                'payer_name'    => data_get($t->provider_payload, 'nome_pagador'),
                'payer_document'=> data_get($t->provider_payload, 'cpf_pagador'),
                'ispb'          => data_get($t->provider_payload, 'ispb'),
            ]);

            $this->sendSignedWebhook($url, $payload, $user, 'in');
        } catch (\Throwable $e) {
            Log::error('Falha ao notificar webhook IN', ['error' => $e->getMessage()]);
        }
    }

    /** Dispara webhook para o cliente quando o saque (OUT) muda (apenas pago). */
    private function notifyUserWebhookOut(Withdraw $w): void
    {
        try {
            $user = $w->user ?? null;
            if (!$user) {
                Log::warning('Webhook OUT: withdraw sem usuário', ['withdraw_id' => $w->id]);
                return;
            }

            $enabled = (bool)($user->webhook_enabled ?? false);
            $url     = (string)($user->webhook_out_url ?? '');

            if (!$enabled || empty($url)) {
                Log::info('Webhook OUT desabilitado/sem URL', ['user_id' => $user->id ?? null, 'withdraw_id' => $w->id]);
                return;
            }

            // Payload OUT sem id/account/provider
            $payload = array_filter([
                'type'       => 'pix.transaction.update',
                'direction'  => 'out',
                'external_reference' => $w->idempotency_key ?? null,
                'txid'       => $w->provider_reference ?? data_get($w->meta, 'provider_txid'),
                'e2e_id'     => data_get($w->meta, 'end_to_end'),
                'amount'     => isset($w->amount) ? (float)$w->amount : null,
                'fee'        => isset($w->fee_amount) ? (float)$w->fee_amount : (float)data_get($w->meta, 'provider_fee', 0),
                'net_amount' => $this->coalesce($w->amount ?? null, null), // ajuste se houver outra coluna líquida
                'currency'   => 'BRL',
                'status'     => (string)($w->status ?? 'processing'),
                'provider_status' => (string)data_get($w->meta, 'provider_status'),
                'timestamps' => [
                    'created_at'   => optional($w->created_at)->toISOString(),
                    'updated_at'   => optional($w->updated_at)->toISOString(),
                    'processed_at' => optional($w->processed_at)->toISOString(),
                ],
                'meta'       => array_filter([
                    'payee_name'     => data_get($w->meta, 'payee_name'),
                    'payee_document' => data_get($w->meta, 'payee_document_mask'),
                    'ispb'           => data_get($w->meta, 'ispb'),
                ], fn ($v) => !is_null($v)),
            ], fn ($v) => !is_null($v));

            $this->sendSignedWebhook($url, $payload, $user, 'out');
        } catch (\Throwable $e) {
            Log::error('Falha ao notificar webhook OUT', ['error' => $e->getMessage()]);
        }
    }

    /** Monta payload base de PIX (IN) — sem id, account e provider. */
    private function makePixPayload(string $type, string $direction, Transaction $t, array $extra = []): array
    {
        return array_filter([
            'type'       => $type,                 // "pix.transaction.update"
            'direction'  => $direction,            // "in"
            'external_reference' => $t->external_reference ?? $t->external_id ?? null,
            'txid'       => $t->txid,
            'e2e_id'     => $t->e2e_id,            // garantido quando pago
            'amount'     => isset($t->amount) ? (float)$t->amount : null,
            'fee'        => isset($t->fee) ? (float)$t->fee : null,
            'net_amount' => $this->tryNetAmount($t),
            'currency'   => $t->currency ?? 'BRL',
            'status'     => $this->enumValue($t->status), // "paga", "pendente", etc.
            // 'provider'  => (removido)
            'provider_status' => data_get($t->provider_payload, 'provider_status'),
            'timestamps' => [
                'created_at'  => optional($t->created_at)->toISOString(),
                'updated_at'  => optional($t->updated_at)->toISOString(),
                'paid_at'     => optional($t->paid_at)->toISOString(),
                'canceled_at' => optional($t->canceled_at)->toISOString(),
            ],
            // 'account'   => (removido)
            'meta'       => array_filter([
                'payer_name'     => $extra['payer_name']     ?? data_get($t->provider_payload, 'nome_pagador'),
                'payer_document' => $extra['payer_document'] ?? data_get($t->provider_payload, 'cpf_pagador'),
                'ispb'           => $extra['ispb']           ?? data_get($t->provider_payload, 'ispb'),
            ], fn ($v) => !is_null($v)),
        ], fn ($v) => !is_null($v));
    }

    /** Envia webhook assinado (retry, timeout) */
    private function sendSignedWebhook(string $url, array $payload, $user, string $flow): void
    {
        // Preferências de segredo: webhook_secret > gtkey > authkey > app.key
        $secret = $user->webhook_secret
            ?? $user->gtkey
            ?? $user->authkey
            ?? config('app.key');

        $ts   = (string) now()->getTimestamp();
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $base  = $ts.'.'.$body;
        $sig   = hash_hmac('sha256', $base, (string)$secret);

        try {
            $resp = Http::timeout(8)
                ->retry(2, 300) // 3 tentativas (1 + 2 retries)
                ->withHeaders([
                    'X-Webhook-Event'      => $payload['type'],
                    'X-Webhook-Provider'   => 'closedpay/veltrax',
                    'X-Webhook-Direction'  => $flow,         // in|out
                    'X-Webhook-Timestamp'  => $ts,
                    'X-Webhook-Signature'  => "sha256={$sig}",
                    'Content-Type'         => 'application/json',
                ])
                ->post($url, $payload);

            Log::info('Webhook cliente enviado', [
                'url' => Str::of($url)->limit(120),
                'status' => $resp->status(),
                'dir' => $flow,
                'event' => $payload['type'],
            ]);
        } catch (\Throwable $e) {
            Log::error('Erro ao enviar webhook cliente', [
                'url'   => Str::of($url)->limit(120),
                'dir'   => $flow,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /* ======================== Helpers ======================== */
    private function hasMeta(string $table): bool
    {
        return Schema::hasTable($table) && Schema::hasColumn($table, 'meta');
    }

    private function hasColumn(string $table, string $column): bool
    {
        return Schema::hasTable($table) && Schema::hasColumn($table, $column);
    }

    /** Converte status (enum|string|null) para string consistente do enum. */
    private function enumValue($status): string
    {
        if ($status instanceof TransactionStatus) {
            return $status->value;
        }
        return TransactionStatus::fromLoose((string)$status)->value;
    }

    /** Tenta obter net_amount (coluna gerada) com fallback. */
    private function tryNetAmount(Transaction $t): ?float
    {
        try {
            if (Schema::hasColumn($t->getTable(), 'net_amount') && !is_null($t->net_amount)) {
                return (float)$t->net_amount;
            }
        } catch (\Throwable $e) {
            // ignore
        }
        $amount = (float)($t->amount ?? 0);
        $fee    = (float)($t->fee ?? 0);
        return $amount > 0 ? round($amount - $fee, 2) : null;
    }

    /** Retorna o primeiro não-nulo (helper pequeno). */
    private function coalesce(...$args)
    {
        foreach ($args as $a) {
            if (!is_null($a)) return $a;
        }
        return null;
    }
}
