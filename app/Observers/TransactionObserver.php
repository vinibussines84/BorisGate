<?php

namespace App\Observers;

use App\Enums\TransactionStatus;
use App\Models\Transaction;
use App\Models\Notification;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

class TransactionObserver
{
    /**
     * Converte valores soltos para Enum real
     */
    private function asEnum(null|string|TransactionStatus $v): ?TransactionStatus
    {
        if ($v instanceof TransactionStatus) return $v;
        if ($v === null || $v === '') return null;
        return TransactionStatus::fromLoose($v);
    }

    /**
     * Aplica timestamps corretos baseado na mudança
     */
    private function applyTimestamps(Transaction $t, ?TransactionStatus $old, TransactionStatus $new): void
    {
        $now = CarbonImmutable::now();

        // PIX pago → seta paid_at se ainda não existe
        if ($new === TransactionStatus::PAID) {
            $t->paid_at = $t->paid_at ?? $now;
        }

        // Se estava pago e voltou para outro status, remove paid_at
        if ($old === TransactionStatus::PAID && $new !== TransactionStatus::PAID) {
            $t->paid_at = null;
        }

        // Quando volta para pendente
        if ($new === TransactionStatus::PENDING && empty($t->authorized_at)) {
            $t->authorized_at = $now;
        }

        // Falhas → marca canceled_at
        if (in_array($new, [TransactionStatus::FAILED, TransactionStatus::ERROR], true)) {
            $t->canceled_at ??= $now;

            // Se estava paga e agora virou falha, remove paid_at
            if ($old === TransactionStatus::PAID) {
                $t->paid_at = null;
            }
        }

        // Se saiu de erro/falha → limpa canceled_at
        if (in_array($old, [TransactionStatus::FAILED, TransactionStatus::ERROR], true)
            && !in_array($new, [TransactionStatus::FAILED, TransactionStatus::ERROR], true)) {
            $t->canceled_at = null;
        }
    }

    /**
     * Evento saving — antes de salvar
     */
    public function saving(Transaction $t): void
    {
        $old = $this->asEnum($t->getOriginal('status')) ?? TransactionStatus::PENDING;
        $new = $this->asEnum($t->status) ?? TransactionStatus::PENDING;

        // Se não houve mudança no status → ignora
        if ($old->value === $new->value) {
            return;
        }

        // Aplica timestamps
        $this->applyTimestamps($t, $old, $new);
    }

    /**
     * Evento created — logo após criar a transação
     */
    public function created(Transaction $t): void
    {
        $new = $this->asEnum($t->status) ?? TransactionStatus::PENDING;

        // Criou uma transação paga? (quase impossível, mas permitido)
        if ($new === TransactionStatus::PAID) {
            Notification::create([
                'user_id' => $t->user_id,
                'title'   => 'Venda paga',
                'message' => "Nova venda paga no valor de R$ " . number_format($t->amount, 2, ',', '.'),
            ]);
        }

        /**
         * 🚫 NÃO MEXE EM CARTEIRA
         * 🚫 NÃO DISPARA WEBHOOK
         * 🚫 NÃO REGRAS FINANCEIRAS AQUI
         *
         * Tudo isso pertence ao Webhook PodPay Controller.
         */
    }

    /**
     * Evento updated — dispara quando status muda
     */
    public function updated(Transaction $t): void
    {
        if (!$t->wasChanged('status')) {
            return;
        }

        $old = $this->asEnum($t->getOriginal('status')) ?? TransactionStatus::PENDING;
        $new = $this->asEnum($t->status) ?? TransactionStatus::PENDING;

        Log::info('TX status changed', [
            'tx_id' => $t->id,
            'from'  => $old->value,
            'to'    => $new->value,
        ]);

        // Notificação interna
        if ($new === TransactionStatus::PAID && $old !== TransactionStatus::PAID) {
            Notification::create([
                'user_id' => $t->user_id,
                'title'   => 'Venda paga',
                'message' => "Nova venda paga no valor de R$ " . number_format($t->amount, 2, ',', '.'),
            ]);
        }

        /**
         * 🚫 SEM WALLET SERVICE
         * 
         * Quem chama applyStatusChange() é o Webhook PodPay Controller.
         * Assim sua carteira fica 100% consistente.
         */

        /**
         * 🚫 SEM WEBHOOK AQUI
         *
         * WebhookPixUpdatedJob é disparado pelo
         * PodPayWebhookController quando recebe status = PAID.
         */
    }
}
