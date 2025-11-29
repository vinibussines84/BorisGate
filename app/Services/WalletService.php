<?php

namespace App\Services;

use App\Enums\TransactionStatus;
use App\Models\Transaction;
use App\Models\User;

class WalletService
{
    /**
     * Aplica efeitos de carteira quando o status muda.
     *
     * Regras principais pedidas:
     * - Entrou em MED => tirar do available EXATAMENTE 1x o valor BRUTO e bloquear o BRUTO.
     * - MED → PENDENTE => desbloqueia (blocked -= bruto aplicado) e NÃO devolve ao available.
     * - MED → PAGA     => libera bloqueio e credita APENAS o líquido em available.
     * - MED → ERRO/FALHA => não altera carteira.
     *
     * Saldos podem negativar.
     */
    public function applyStatusChange(Transaction $t, ?TransactionStatus $old, TransactionStatus $new): void
    {
        /** @var User|null $u */
        $u = $t->user()->lockForUpdate()->first();
        if (! $u) return;

        $isCashIn = ($t->direction === 'in');

        $gross = (float) $t->amount;
        $net   = $isCashIn ? $this->calcNetForUser($u, $gross) : $gross;

        $prevAppliedAvail = (float) ($t->applied_available_amount ?? 0.0);
        $prevAppliedBlock = (float) ($t->applied_blocked_amount   ?? 0.0);

        // ============================
        // ENTRADA EM MED (1ª vez)
        // ============================
        if ($isCashIn && $new === TransactionStatus::MED && $prevAppliedBlock <= 0.0) {
            // Regra: retirar do available UMA ÚNICA VEZ o BRUTO e bloquear BRUTO
            $u->amount_available = (float) $u->amount_available - $gross;
            $u->blocked_amount   = (float) $u->blocked_amount   + $gross;
            $u->save();

            // Atualiza rastro: agora há BRUTO bloqueado; nada aplicado em available por esta transação
            $t->applied_available_amount = 0.0;
            $t->applied_blocked_amount   = $gross;
            return;
        }

        // ============================
        // CASOS ESPECIAIS: MED -> ...
        // ============================
        if ($isCashIn && $old === TransactionStatus::MED) {

            // MED → PENDENTE: tirar do blocked e NÃO devolver ao available (valor “some”)
            if ($new === TransactionStatus::PENDENTE) {
                $u->blocked_amount = (float) $u->blocked_amount - $prevAppliedBlock;
                $u->save();

                $t->applied_available_amount = 0.0;
                $t->applied_blocked_amount   = 0.0;
                return;
            }

            // MED → PAGA: liberar bloqueio (bruto) e creditar LÍQUIDO em available
            if ($new === TransactionStatus::PAGA) {
                $u->blocked_amount   = (float) $u->blocked_amount - $prevAppliedBlock;
                $u->amount_available = (float) $u->amount_available + $net;
                $u->save();

                $t->applied_available_amount = $net;
                $t->applied_blocked_amount   = 0.0;
                return;
            }

            // MED → ERRO / FALHA: não mexe na carteira
            if (in_array($new, [TransactionStatus::ERRO, TransactionStatus::FALHA], true)) {
                return;
            }
            // Outras transições a partir de MED caem na regra padrão mais abaixo
        }

        // ============================
        // CASO ESPECIAL: PAGA → MED (já vinha pago)
        // ============================
        if ($isCashIn && $old === TransactionStatus::PAGA && $new === TransactionStatus::MED) {
            // Regra do cliente: ao bloquear, retirar do available o BRUTO 1x e bloquear BRUTO
            // IMPORTANTE: não subtrair "de novo" nada além do BRUTO, nem remover o líquido previamente.
            if ($prevAppliedBlock <= 0.0) {
                $u->amount_available = (float) $u->amount_available - $gross;
                $u->blocked_amount   = (float) $u->blocked_amount   + $gross;
                $u->save();

                $t->applied_available_amount = 0.0;
                $t->applied_blocked_amount   = $gross;
            }
            return;
        }

        // ============================
        // REGRA PADRÃO (demais casos)
        // ============================
        $targetAppliedAvail = 0.0;
        $targetAppliedBlock = 0.0;

        if ($isCashIn) {
            switch ($new) {
                case TransactionStatus::PAGA:
                    // credita LÍQUIDO
                    $targetAppliedAvail = $net;
                    $targetAppliedBlock = 0.0;
                    break;

                case TransactionStatus::MED:
                    // Se caiu aqui é porque já havia bloqueio aplicado; não reaplica
                    $targetAppliedAvail = 0.0;
                    $targetAppliedBlock = $prevAppliedBlock > 0.0 ? $prevAppliedBlock : 0.0;
                    break;

                case TransactionStatus::PENDENTE:
                case TransactionStatus::FALHA:
                case TransactionStatus::ERRO:
                default:
                    $targetAppliedAvail = 0.0;
                    $targetAppliedBlock = 0.0;
                    break;
            }
        } else {
            // cash-out: personalize conforme sua regra
            $targetAppliedAvail = 0.0;
            $targetAppliedBlock = 0.0;
        }

        // deltas padrão (bloqueio consome available quando aumenta)
        $deltaBlock = $targetAppliedBlock - $prevAppliedBlock;
        $deltaAvail = ($targetAppliedAvail - $prevAppliedAvail) - $deltaBlock;

        $u->amount_available = (float) $u->amount_available + $deltaAvail;
        $u->blocked_amount   = (float) $u->blocked_amount   + $deltaBlock;
        $u->save();

        $t->applied_available_amount = $targetAppliedAvail;
        $t->applied_blocked_amount   = $targetAppliedBlock;
    }

    /**
     * Calcula o líquido (gross - taxa do usuário) para CASH-IN.
     * Suporta: tax_in_enabled, tax_in_mode ('fixo'|'percentual'), tax_in_fixed, tax_in_percent.
     */
    private function calcNetForUser(User $u, float $gross): float
    {
        if (! $u->tax_in_enabled) {
            return round($gross, 2);
        }

        $mode = (string) ($u->tax_in_mode ?? 'percentual');

        if ($mode === 'fixo') {
            $fee = (float) ($u->tax_in_fixed ?? 0);
        } else {
            $pct = (float) ($u->tax_in_percent ?? 0);
            $fee = $pct > 0 ? ($gross * ($pct / 100)) : 0.0;
        }

        $net = $gross - $fee;
        return round($net, 2);
    }
}
