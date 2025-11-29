<?php

namespace App\Casts;

use App\Enums\TransactionStatus;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use InvalidArgumentException;

class TransactionStatusCast implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes): ?TransactionStatus
    {
        if ($value === null || $value === '') return null;

        // Converte qualquer valor legado ("pending", "paid", etc.) para o enum correto
        return TransactionStatus::fromLoose((string) $value);
    }

    public function set($model, string $key, $value, array $attributes): ?string
    {
        if ($value === null || $value === '') return null;

        if ($value instanceof TransactionStatus) {
            return $value->value;
        }

        // Aceita strings soltas e normaliza
        try {
            return TransactionStatus::fromLoose((string) $value)->value;
        } catch (\Throwable $e) {
            throw new InvalidArgumentException("Status inválido: {$value}");
        }
    }
}
