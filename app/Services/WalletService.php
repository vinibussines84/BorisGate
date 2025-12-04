<?php

namespace App\Services;

use App\Enums\TransactionStatus;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class WalletService
{
    /**
     * Aplica alterações de saldo ao mudar o status da TX.
     * Regra simples e correta para cash-in (PIX recebido).
     */
    public function applyStatusChange(Transaction $t, ?TransactionStatus $old, TransactionStatus $new): void
    {
        // PIX Cash-in apenas
        if ($t->direction !== 'in') {
            return;
        }

        DB::transaction(function () use ($t, $old, $new) {

            /** LOCK no usuário */
            $u = User::lockForUpdate()->find($t->user_id);
            if (!$u) return;

            $gross = (float) $t->amount;
            $net   = $this->calcNetForUser($u, $gross);

            /**
             * 🔐 Idempotência REAL:
             * Se a transação já está paga, nunca reaplica.
             */
            if (
                $old === TransactionStatus::PAID ||
                $t->status === TransactionStatus::PAID->value
            ) {
                return;
            }

            /**
             * 🚀 Regra PRINCIPAL:
             * Só credita quando o status FINAL vira PAID.
             */
            if ($new === TransactionStatus::PAID) {

                // Creditar líquido ao usuário
                $u->updateQuietly([
                    'amount_available' => round($u->amount_available + $net, 2),
                ]);

                // Registrar rastro aplicado
                $t->updateQuietly([
                    'applied_available_amount' => $net,
                    'applied_blocked_amount'   => 0,
                ]);
            }

            // Qualquer outro status não altera carteira
        });
    }

    /**
     * Calcula o valor líquido após taxas do usuário (cash-in).
     */
    private function calcNetForUser(User $u, float $gross): float
    {
        // Sem taxa → líquido = bruto
        if (!$u->tax_in_enabled) {
            return round($gross, 2);
        }

        // Taxa fixa
        if (($u->tax_in_mode ?? 'percentual') === 'fixo') {
            return round($gross - (float)$u->tax_in_fixed, 2);
        }

        // Percentual
        $fee = $gross * ((float)$u->tax_in_percent / 100);
        return round($gross - $fee, 2);
    }
}
