<?php

namespace App\Enums;

enum TransactionStatus: string
{
    case FALHA        = 'falha';
    case ERRO         = 'erro';
    case PAGA         = 'paga';
    case PENDENTE     = 'pendente';
    case MED          = 'med';            // legado
    case UNDER_REVIEW = 'under_review';   // novo status oficial p/ análise manual

    /** 🔤 Rótulo humano */
    public function label(): string
    {
        return match ($this) {
            self::FALHA        => 'Falha',
            self::ERRO         => 'Erro',
            self::PAGA         => 'Paga',
            self::PENDENTE     => 'Pendente',
            self::MED          => 'Med',
            self::UNDER_REVIEW => 'Em Análise',
        };
    }

    /** 🎨 Cores para front/Filament */
    public function color(): string
    {
        return match ($this) {
            self::FALHA        => 'danger',
            self::ERRO         => 'warning',
            self::PAGA         => 'success',
            self::PENDENTE     => 'secondary',
            self::MED          => 'info',
            self::UNDER_REVIEW => 'warning', // amarelo, padrão p/ análise manual
        };
    }

    /**
     * 🧠 Normalização inteligente de strings → enum válido
     * Aceita variações em PT-BR e EN-US
     */
    public static function fromLoose(string $value): self
    {
        $v = strtolower(trim($value));

        return match ($v) {
            'falha', 'failed', 'fail'             => self::FALHA,
            'erro', 'error'                       => self::ERRO,
            'paga', 'paid'                        => self::PAGA,
            'pendente', 'pending'                 => self::PENDENTE,
            'med', 'mediacao', 'mediation'        => self::MED,

            // novas variações reconhecidas
            'under_review', 'em_analise', 'analise', 'review' 
                => self::UNDER_REVIEW,

            default => self::PENDENTE, // fallback seguro
        };
    }
}
