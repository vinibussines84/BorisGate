<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebhookLog extends Model
{
    use HasFactory;

    /**
     * Nome da tabela.
     */
    protected $table = 'webhook_logs';

    /**
     * Campos liberados para atribuição em massa.
     */
    protected $fillable = [
        'user_id',
        'type',           // 'in' ou 'out'
        'url',
        'payload',
        'status',         // success | error
        'response_code',
        'response_body',
    ];

    /**
     * Tipos de conversão automática de atributos.
     */
    protected $casts = [
        'payload' => 'array',  // ✅ Garante que payload seja sempre array PHP
    ];

    /**
     * Retorna o usuário dono do log.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Helper: status colorido (útil em Blade/Inertia).
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'success' => 'text-green-500',
            'error'   => 'text-red-500',
            default   => 'text-gray-400',
        };
    }

    /**
     * Helper: data formatada (legível para listagem).
     */
    public function getFormattedDateAttribute(): string
    {
        return optional($this->created_at)
            ->setTimezone('America/Sao_Paulo')
            ->format('d/m/Y H:i');
    }
}
