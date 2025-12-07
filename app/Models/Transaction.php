<?php

namespace App\Models;

use App\Enums\TransactionStatus;
use App\Support\StatusMap;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    use SoftDeletes;

    public const DIR_IN  = 'in';
    public const DIR_OUT = 'out';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'amount',
        'fee',
        'direction',
        'status',
        'currency',
        'method',
        'provider',
        'provider_transaction_id',
        'external_id',
        'external_reference',
        'txid',
        'e2e_id',
        'payer_name',
        'payer_document',
        'provider_payload',
        'description',
        'authorized_at',
        'paid_at',       // <-- manter
        'refunded_at',
        'canceled_at',
        'idempotency_key',
        'ip',
        'user_agent',
        'applied_available_amount',
        'applied_blocked_amount',
    ];

    protected $casts = [
        'amount'                     => 'decimal:2',
        'fee'                        => 'decimal:2',
        'net_amount'                 => 'decimal:2',
        'provider_payload'           => 'array',
        // CORRIGIDO — NÃO CONVERTER MAIS PARA UTC AUTOMATICAMENTE
        'authorized_at'              => 'datetime',
        'paid_at'                    => 'string',   // <── AQUI ESTÁ A SOLUÇÃO
        'refunded_at'                => 'datetime',
        'canceled_at'                => 'datetime',
        'applied_available_amount'   => 'decimal:2',
        'applied_blocked_amount'     => 'decimal:2',
    ];

    protected $appends = [
        'status_label',
        'status_color',
        'pix_code',
        'pix_expire',
        'pix_expires_at',
        'pix_expired',
    ];

    /* ============================================================
       MUTATOR FINAL QUE NUNCA ALTERA O HORÁRIO
       Mesmo que venha com timezone ou sem.
    ============================================================ */
    public function setPaidAtAttribute($value): void
    {
        if (!$value) {
            $this->attributes['paid_at'] = null;
            return;
        }

        // Sempre salva a STRING EXATA enviada pelo provedor
        $this->attributes['paid_at'] = (string) $value;
    }

    /* ============================================================
       STATUS
    ============================================================ */
    public function setStatusAttribute($value): void
    {
        $normalized = $value instanceof TransactionStatus
            ? $value->value
            : StatusMap::normalize((string) $value);

        $this->attributes['status'] = strtoupper($normalized);
    }

    public function setDirectionAttribute($value): void
    {
        $value = strtolower($value);
        $this->attributes['direction'] =
            in_array($value, ['in', 'out']) ? $value : 'in';
    }

    public function setTxidAttribute($value): void
    {
        $v = preg_replace('/[^A-Za-z0-9\-\._]/', '', (string)$value);
        $this->attributes['txid'] = $v ? substr($v, 0, 64) : null;
    }

    public function setProviderTransactionIdAttribute($value): void
    {
        $v = preg_replace('/[^A-Za-z0-9\-\._]/', '', (string)$value);
        $this->attributes['provider_transaction_id'] =
            $v ? substr($v, 0, 100) : null;
    }

    public function setE2eIdAttribute($value): void
    {
        $v = preg_replace('/[^A-Za-z0-9\-\.]/', '', (string)$value);
        $this->attributes['e2e_id'] = $v ? substr($v, 0, 100) : null;
    }

    /* ============================================================
       ACCESSORS (não mexidos)
    ============================================================ */

    protected function statusLabel(): Attribute
    {
        return Attribute::get(fn () =>
            ($this->statusEnum()?->label()) ?? '—'
        );
    }

    protected function statusColor(): Attribute
    {
        return Attribute::get(fn () =>
            ($this->statusEnum()?->color()) ?? 'secondary'
        );
    }

    protected function statusEnum(): ?TransactionStatus
    {
        $raw = $this->attributes['status'] ?? null;
        return $raw ? TransactionStatus::tryFrom(strtoupper($raw)) : null;
    }

    /* defaults */
    protected static function booted(): void
    {
        static::creating(function (self $m) {
            $m->direction = $m->direction ?: self::DIR_IN;
            $m->currency  = $m->currency  ?: 'BRL';
            $m->method    = $m->method    ?: 'pix';
            $m->applied_available_amount = $m->applied_available_amount ?? 0;
            $m->applied_blocked_amount   = $m->applied_blocked_amount ?? 0;
        });
    }
}
