<?php

namespace App\Enums;

enum TransactionStatus: string
{
    case FAILED     = 'FAILED';
    case ERROR      = 'ERROR';
    case PAID       = 'PAID';
    case PENDING    = 'PENDING';
    case PROCESSING = 'PROCESSING';

    /** Rótulo humano */
    public function label(): string
    {
        return match ($this) {
            self::FAILED     => 'Falha',
            self::ERROR      => 'Erro Interno',
            self::PAID       => 'Paga',
            self::PENDING    => 'Pendente',
            self::PROCESSING => 'Em Processamento',
        };
    }

    /** Cor para Filament */
    public function color(): string
    {
        return match ($this) {
            self::FAILED     => 'danger',
            self::ERROR      => 'warning',
            self::PAID       => 'success',
            self::PENDING    => 'secondary',
            self::PROCESSING => 'info',
        };
    }

    /**
     * Converte loose status → Enum real
     */
    public static function fromLoose(string $value): self
    {
        return self::tryFrom(
            \App\Support\StatusMap::normalize($value)
        ) ?? self::PENDING;
    }
}
