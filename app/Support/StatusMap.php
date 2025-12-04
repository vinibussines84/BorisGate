<?php

namespace App\Support;

class StatusMap
{
    /**
     * Status oficiais da API:
     *
     * - PENDING
     * - PROCESSING
     * - PAID
     * - FAILED
     * - ERROR
     */
    public static function normalize(?string $status): string
    {
        if (!$status) {
            return 'ERROR';
        }

        $status = strtolower(trim($status));

        return match ($status) {

            // ---------------------------
            // PENDING
            // ---------------------------
            'pending',
            'pendente',
            'waiting',
            'created',
            'initiated',
            'iniciado',
            'new',
            'open' => 'PENDING',

            // ---------------------------
            // PROCESSING
            // ---------------------------
            'processing',
            'processando',
            'in_analysis',
            'analise' => 'PROCESSING',

            // ---------------------------
            // PAID
            // ---------------------------
            'paid',
            'approved',
            'completed',
            'success',
            'confirmed',
            'aprovado',
            'confirmado' => 'PAID',

            // ---------------------------
            // FAILED
            // ---------------------------
            'failed',
            'rejected',
            'canceled',
            'cancelled',
            'refused',
            'declined',
            'denied',
            'falhou',
            'rejeitado',
            'cancelado' => 'FAILED',

            // ---------------------------
            // ERROR
            // ---------------------------
            'error',
            'erro',
            'internal_error',
            'provider_error' => 'ERROR',

            // ---------------------------
            // DEFAULT
            // ---------------------------
            default => 'PENDING',
        };
    }
}
