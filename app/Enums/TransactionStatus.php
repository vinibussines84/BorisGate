<?php

namespace App\Enums;

enum TransactionStatus: string
{
    case FALHA        = 'falha';        // falha financeira real
    case ERRO         = 'erro';         // erro interno / técnico
    case PAGA         = 'paga';
    case PENDENTE     = 'pendente';
    case MED          = 'med';          // legado (mediação)
    case UNDER_REVIEW = 'under_review'; // análise manual

    /** Rótulo humano */
    public function label(): string
    {
        return match ($this) {
            self::FALHA        => 'Falha',
            self::ERRO         => 'Erro Interno',
            self::PAGA         => 'Paga',
            self::PENDENTE     => 'Pendente',
            self::MED          => 'Em Mediação',
            self::UNDER_REVIEW => 'Em Análise',
        };
    }

    /** Cor para Filament */
    public function color(): string
    {
        return match ($this) {
            self::FALHA        => 'danger',
            self::ERRO         => 'warning',
            self::PAGA         => 'success',
            self::PENDENTE     => 'secondary',
            self::MED          => 'info',
            self::UNDER_REVIEW => 'warning',
        };
    }

    /**
     * 🔥 Normalização inteligente e compatível com PodPay
     */
    public static function fromLoose(string $value): self
    {
        $v = strtolower(trim($value));

        return match ($v) {
            // Falhas comuns de gateways
            'failed', 'fail', 'canceled', 'cancelled',
            'refused', 'denied', 'rejected',
            'expired', 'returned'              => self::FALHA,

            // Erros internos
            'erro', 'error'                    => self::ERRO,

            // Pago
            'paga', 'paid', 'approved', 'confirmed'
                                                => self::PAGA,

            // Pendente
            'pendente', 'pending', 'waiting'    => self::PENDENTE,

            // Mediação / processamento
            'med', 'mediation', 'processing',
            'created', 'authorized'            => self::MED,

            // Análise manual
            'under_review', 'em_analise', 'review'
                                                => self::UNDER_REVIEW,

            // Fallback SEGURO → MED
            default                             => self::MED,
        };
    }
}
