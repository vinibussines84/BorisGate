<?php

namespace App\Services;

use App\Enums\TransactionStatus;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class WalletService
{
    /**
     * Aplica alterações de saldo de forma SEGURA e ATÔMICA.
     * Compatível com todos os status atualizados.
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
            $prevAppliedAvail = round((float) $t->applied_available_amount ?? 0, 2);
            $prevAppliedBlock = round((float) $t->applied_blocked_amount   ?? 0, 2);

            /**
             * ============================================================
             * STATUS ESPECIAL: UNDER_REVIEW 🟡
             * - Funciona EXATAMENTE como MED
             * ============================================================
             */
            if ($new === TransactionStatus::UNDER_REVIEW) {
                $new = TransactionStatus::MED;
            }


            /**
             * ============================================================
             * ENTRADA EM MED (1ª vez)  → bloqueia BRUTO uma vez
             * ============================================================
             */
            if ($isCashIn && $new === TransactionStatus::MED && $prevAppliedBlock <= 0) {

                $u->amount_available = round($u->amount_available - $gross, 2);
                $u->blocked_amount   = round($u->blocked_amount + $gross, 2);
                $u->save();

                $t->applied_available_amount = 0;
                $t->applied_blocked_amount   = $gross;
                $t->save();
                return;
            }


            /**
             * ============================================================
             * MED → alguma coisa
             * ============================================================
             */
            if ($isCashIn && $old === TransactionStatus::MED) {

                // MED → PENDENTE
                if ($new === TransactionStatus::PENDENTE) {

                    $u->blocked_amount = round($u->blocked_amount - $prevAppliedBlock, 2);
                    $u->save();

                    $t->applied_available_amount = 0;
                    $t->applied_blocked_amount   = 0;
                    $t->save();
                    return;
                }

                // MED → PAGA
                if ($new === TransactionStatus::PAGA) {

                    $u->blocked_amount   = round($u->blocked_amount - $prevAppliedBlock, 2);
                    $u->amount_available = round($u->amount_available + $net, 2);
                    $u->save();

                    $t->applied_available_amount = $net;
                    $t->applied_blocked_amount   = 0;
                    $t->save();
                    return;
                }

                // MED → ERRO ou FALHA → não modifica carteira
                if (in_array($new, [TransactionStatus::ERRO, TransactionStatus::FALHA], true)) {
                    return;
                }
            }


            /**
             * ============================================================
             * PAGA → MED (Reversão rara)
             * ============================================================
             */
            if ($isCashIn && $old === TransactionStatus::PAGA && $new === TransactionStatus::MED) {

                // Bloqueia novamente o bruto apenas 1x
                if ($prevAppliedBlock <= 0) {
                    $u->amount_available = round($u->amount_available - $gross, 2);
                    $u->blocked_amount   = round($u->blocked_amount + $gross, 2);
                    $u->save();

                    $t->applied_available_amount = 0;
                    $t->applied_blocked_amount   = $gross;
                    $t->save();
                }

                return;
            }


            /**
             * ============================================================
             * REGRA PADRÃO PARA CASH-IN
             * ============================================================
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

                    case TransactionStatus::PENDENTE:
                    case TransactionStatus::ERRO:
                    case TransactionStatus::FALHA:
                    default:
                        break;
                }
            }

            /** Deltas financeiros */
            $deltaBlock = round($targetAppliedBlock - $prevAppliedBlock, 2);
            $deltaAvail = round(($targetAppliedAvail - $prevAppliedAvail) - $deltaBlock, 2);

            /** Atualiza saldos */
            $u->amount_available = round($u->amount_available + $deltaAvail, 2);
            $u->blocked_amount   = round($u->blocked_amount + $deltaBlock, 2);
            $u->save();

            /** Atualiza rastro */
            $t->applied_available_amount = $targetAppliedAvail;
            $t->applied_blocked_amount   = $targetAppliedBlock;
            $t->save();
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
