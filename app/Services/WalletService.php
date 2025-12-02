<?php

namespace App\Services;

use App\Enums\TransactionStatus;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class WalletService
{
    /**
     * Aplica alterações de saldo sem disparar Observer novamente.
     * TOTALMENTE à prova de loop.
     */
    public function applyStatusChange(Transaction $t, ?TransactionStatus $old, TransactionStatus $new): void
    {
        DB::transaction(function () use ($t, $old, $new) {

            /** Lock no usuário */
            $u = User::where('id', $t->user_id)
                ->lockForUpdate()
                ->first();

            if (!$u) {
                return;
            }

            /** Direção válida */
            if (!in_array($t->direction, ['in', 'out'])) {
                throw new \Exception("Invalid transaction direction: {$t->direction}");
            }

            $isCashIn = ($t->direction === 'in');

            /** Valores */
            $gross = round((float) $t->amount, 2);
            $net   = $isCashIn ? $this->calcNetForUser($u, $gross) : $gross;

            /** Rastro anterior */
            $prevAppliedAvail = round((float) ($t->applied_available_amount ?? 0), 2);
            $prevAppliedBlock = round((float) ($t->applied_blocked_amount ?? 0), 2);

            /**
             * STATUS UNDER_REVIEW = MED
             */
            if ($new === TransactionStatus::UNDER_REVIEW) {
                $new = TransactionStatus::MED;
            }

            /**
             * ------------------------------------------
             * 1️⃣ MED (1ª vez) → Bloqueia BRUTO uma vez
             * ------------------------------------------
             */
            if ($isCashIn && $new === TransactionStatus::MED && $prevAppliedBlock <= 0) {

                $u->updateQuietly([
                    'amount_available' => round($u->amount_available - $gross, 2),
                    'blocked_amount'   => round($u->blocked_amount + $gross, 2),
                ]);

                $t->updateQuietly([
                    'applied_available_amount' => 0,
                    'applied_blocked_amount'   => $gross,
                ]);

                return;
            }

            /**
             * ------------------------------------------
             * 2️⃣ MED → algum status
             * ------------------------------------------
             */
            if ($isCashIn && $old === TransactionStatus::MED) {

                // MED → PENDENTE
                if ($new === TransactionStatus::PENDENTE) {

                    $u->updateQuietly([
                        'blocked_amount' => round($u->blocked_amount - $prevAppliedBlock, 2),
                    ]);

                    $t->updateQuietly([
                        'applied_available_amount' => 0,
                        'applied_blocked_amount'   => 0,
                    ]);

                    return;
                }

                // MED → PAGA
                if ($new === TransactionStatus::PAGA) {

                    $u->updateQuietly([
                        'blocked_amount'   => round($u->blocked_amount - $prevAppliedBlock, 2),
                        'amount_available' => round($u->amount_available + $net, 2),
                    ]);

                    $t->updateQuietly([
                        'applied_available_amount' => $net,
                        'applied_blocked_amount'   => 0,
                    ]);

                    return;
                }

                // MED → ERRO/FALHA → não altera carteira
                if (in_array($new, [TransactionStatus::ERRO, TransactionStatus::FALHA], true)) {
                    return;
                }
            }

            /**
             * ------------------------------------------------
             * 3️⃣ PAGA → MED (reversão rara) — bloqueia bruto
             * ------------------------------------------------
             */
            if ($isCashIn && $old === TransactionStatus::PAGA && $new === TransactionStatus::MED) {

                if ($prevAppliedBlock <= 0) {

                    $u->updateQuietly([
                        'amount_available' => round($u->amount_available - $gross, 2),
                        'blocked_amount'   => round($u->blocked_amount + $gross, 2),
                    ]);

                    $t->updateQuietly([
                        'applied_available_amount' => 0,
                        'applied_blocked_amount'   => $gross,
                    ]);
                }

                return;
            }

            /**
             * ---------------------------------------------
             * 4️⃣ REGRA PADRÃO (somente Cash-In)
             * ---------------------------------------------
             */
            $targetAppliedAvail = 0.0;
            $targetAppliedBlock = 0.0;

            if ($isCashIn) {

                switch ($new) {

                    case TransactionStatus::PAGA:
                        $targetAppliedAvail = $net;
                        break;

                    case TransactionStatus::MED:
                        if ($prevAppliedBlock > 0) {
                            $targetAppliedBlock = $prevAppliedBlock;
                        }
                        break;

                    default:
                        // PENDENTE, ERRO, FALHA → zerado mesmo
                        break;
                }
            }

            /** Deltas */
            $deltaBlock = round($targetAppliedBlock - $prevAppliedBlock, 2);
            $deltaAvail = round(($targetAppliedAvail - $prevAppliedAvail) - $deltaBlock, 2);

            /** Atualiza saldo */
            $u->updateQuietly([
                'amount_available' => round($u->amount_available + $deltaAvail, 2),
                'blocked_amount'   => round($u->blocked_amount + $deltaBlock, 2),
            ]);

            /** Atualiza rastro */
            $t->updateQuietly([
                'applied_available_amount' => $targetAppliedAvail,
                'applied_blocked_amount'   => $targetAppliedBlock,
            ]);
        });
    }

    /**
     * Calcula o líquido do usuário (cash-in)
     */
    private function calcNetForUser(User $u, float $gross): float
    {
        if (!$u->tax_in_enabled) {
            return round($gross, 2);
        }

        $mode = (string) ($u->tax_in_mode ?? 'percentual');

        if ($mode === 'fixo') {
            $fee = (float) ($u->tax_in_fixed ?? 0);
        } else {
            $pct = (float) ($u->tax_in_percent ?? 0);
            $fee = $pct > 0 ? ($gross * ($pct / 100)) : 0;
        }

        return round($gross - $fee, 2);
    }
}
