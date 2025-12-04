<?php

namespace App\Support;

class StatusMap
{
    /**
     * Normaliza status de QUALQUER provedor.
     */
    public static function normalize(?string $status): string
    {
        if (!$status) {
            return 'ERROR';
        }

        $status = strtolower(trim($status));

        return match ($status) {

            // ---------- PENDING ---------
            'pending', 'pendente', 'waiting', 'waiting_payment',
            'created', 'initiated', 'new', 'open'
                => 'PENDING',

            // ---------- PROCESSING ---------
            'processing', 'processando', 'in_analysis', 'analise',
            'analyzing', 'processing_payment', 'under_review'
                => 'PROCESSING',

            // ---------- PAID ---------
            'paid', 'paga', 'approved', 'completed', 'success',
            'succeeded', 'confirmed', 'aprovado', 'confirmado'
                => 'PAID',

            // ---------- FAILED ---------
            'failed', 'rejected', 'refused', 'declined', 'denied',
            'error_payment', 'falhou', 'rejeitado', 'cancelado',
            'canceled', 'cancelled', 'expired', 'timeout', 'chargeback',
            'returned'
                => 'FAILED',

            // ---------- ERROR ---------
            'error', 'erro', 'internal_error', 'provider_error',
            'unknown', 'invalid'
                => 'ERROR',

            // ---------- DEFAULT ---------
            default => 'PENDING',
        };
    }
}
