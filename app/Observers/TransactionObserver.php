<?php

namespace App\Observers;

use App\Enums\TransactionStatus;
use App\Models\Transaction;
use App\Models\Notification;
use App\Services\WalletService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TransactionObserver
{
    public function __construct(private WalletService $wallet) {}

    private function asEnum(null|string|TransactionStatus $v): ?TransactionStatus
    {
        if ($v instanceof TransactionStatus) return $v;
        if ($v === null || $v === '') return null;
        return TransactionStatus::fromLoose((string) $v);
    }

    private function applyTimestamps(Transaction $t, ?TransactionStatus $old, TransactionStatus $new): void
    {
        $now = CarbonImmutable::now();

        if ($new === TransactionStatus::PAGA) {
            $t->paid_at = $t->paid_at ?? $now;
        }

        if ($old === TransactionStatus::PAGA && $new !== TransactionStatus::PAGA) {
            $t->paid_at = null;
        }

        if ($new === TransactionStatus::PENDENTE && empty($t->authorized_at)) {
            $t->authorized_at = $now;
        }

        if (in_array($new, [TransactionStatus::FALHA, TransactionStatus::ERRO], true)) {
            $t->canceled_at ??= $now;
            if ($old === TransactionStatus::PAGA) {
                $t->paid_at = null;
            }
        }

        if (in_array($old, [TransactionStatus::FALHA, TransactionStatus::ERRO], true)
            && ! in_array($new, [TransactionStatus::FALHA, TransactionStatus::ERRO], true)) {
            $t->canceled_at = null;
        }
    }

    public function saving(Transaction $t): void
    {
        $old = $this->asEnum($t->getOriginal('status')) ?? TransactionStatus::PENDENTE;
        $new = $this->asEnum($t->status) ?? TransactionStatus::PENDENTE;

        if ($old->value === $new->value) {
            return;
        }

        $this->applyTimestamps($t, $old, $new);
    }

    public function created(Transaction $t): void
    {
        $new = $this->asEnum($t->status) ?? TransactionStatus::PENDENTE;

        // Notificação automática no status PAGA
        if ($new === TransactionStatus::PAGA) {
            Notification::create([
                'user_id' => $t->user_id,
                'title'   => 'Venda paga',
                'message' => "Nova venda paga no valor de R$ " . number_format($t->amount, 2, ',', '.'),
            ]);
        }

        // Atualização de carteira
        if (in_array($new, [TransactionStatus::PAGA, TransactionStatus::MED], true)) {
            try {
                DB::transaction(function () use ($t, $new) {
                    $this->wallet->applyStatusChange($t, TransactionStatus::PENDENTE, $new);
                });
            } catch (\Throwable $e) {
                Log::error('Wallet apply on created failed', [
                    'tx_id' => $t->id,
                    'from'  => TransactionStatus::PENDENTE->value,
                    'to'    => $new->value,
                    'err'   => $e->getMessage(),
                ]);
            }
        }

        // ❌ Webhook removido DEFINITIVAMENTE
    }

    public function updated(Transaction $t): void
    {
        if (!$t->wasChanged('status')) {
            return;
        }

        $old = $this->asEnum($t->getOriginal('status')) ?? TransactionStatus::PENDENTE;
        $new = $this->asEnum($t->status) ?? TransactionStatus::PENDENTE;

        if ($old->value === $new->value) {
            return;
        }

        Log::info('TX status changed', [
            'tx_id' => $t->id,
            'from'  => $old->value,
            'to'    => $new->value,
        ]);

        // Notificação quando muda pra PAGA
        if ($new === TransactionStatus::PAGA && $old !== TransactionStatus::PAGA) {
            Notification::create([
                'user_id' => $t->user_id,
                'title'   => 'Venda paga',
                'message' => "Nova venda paga no valor de R$ " . number_format($t->amount, 2, ',', '.'),
            ]);
        }

        // Atualização de carteira
        try {
            DB::transaction(function () use ($t, $old, $new) {
                $this->wallet->applyStatusChange($t, $old, $new);
            });
        } catch (\Throwable $e) {
            Log::error('Wallet apply on updated failed', [
                'tx_id' => $t->id,
                'from'  => $old->value,
                'to'    => $new->value,
                'err'   => $e->getMessage(),
            ]);
        }

        // ❌ Webhook completamente removido do updated
        // $this->sendWebhook($t, 'updated');
    }
}
