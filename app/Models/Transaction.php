<?php

namespace App\Models;

use App\Enums\TransactionStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

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

        // 🔑 Referências externas
        'external_id',
        'external_reference',

        // 📌 PIX
        'txid',
        'e2e_id',

        // 🧾 Dados do pagador (payer)
        'payer_name',
        'payer_document',

        // 🔍 RAW JSON vindo do provider
        'provider_payload',

        // 🔎 Metadados
        'description',
        'authorized_at',
        'paid_at',
        'refunded_at',
        'canceled_at',
        'idempotency_key',
        'ip',
        'user_agent',
        'applied_available_amount',
        'applied_blocked_amount',
    ];

    protected $casts = [
        'amount'           => 'decimal:2',
        'fee'              => 'decimal:2',
        'net_amount'       => 'decimal:2',
        'provider_payload' => 'array',
        'authorized_at'    => 'datetime',
        'paid_at'          => 'datetime',
        'refunded_at'      => 'datetime',
        'canceled_at'      => 'datetime',
        'applied_available_amount' => 'decimal:2',
        'applied_blocked_amount'   => 'decimal:2',
    ];

    protected $appends = [
        'status_label',
        'status_color',
        'pix_code',
        'pix_expire',
        'pix_expires_at',
        'pix_expired',
    ];

    // ================= RELAÇÕES =================
    public function tenant()
    {
        return $this->belongsTo(\App\Models\Tenant::class);
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    // ================= HELPERS =================
    protected function statusEnum(): ?TransactionStatus
    {
        $raw = $this->attributes['status'] ?? null;
        if ($raw === null) return null;
        return TransactionStatus::fromLoose((string) $raw);
    }

    // ================= SCOPES =================
    public function scopePaga($q)
    {
        return $q->where('status', TransactionStatus::PAGA->value);
    }

    public function scopePendente($q)
    {
        return $q->where('status', TransactionStatus::PENDENTE->value);
    }

    public function scopeFalha($q)
    {
        return $q->where('status', TransactionStatus::FALHA->value);
    }

    public function scopeErro($q)
    {
        return $q->where('status', TransactionStatus::ERRO->value);
    }

    public function scopeMed($q)
    {
        return $q->where('status', TransactionStatus::MED->value);
    }

    public function scopeCashIn($q)
    {
        return $q->where('direction', self::DIR_IN);
    }

    public function scopeCashOut($q)
    {
        return $q->where('direction', self::DIR_OUT);
    }

    public function scopeDeCobranca($q)
    {
        return $q->where('external_reference', 'like', 'cobranca:%');
    }

    // ================= ACCESSORS =================
    protected function statusLabel(): Attribute
    {
        return Attribute::get(fn () =>
            $this->statusEnum()?->label() ?? '—'
        );
    }

    protected function statusColor(): Attribute
    {
        return Attribute::get(fn () =>
            $this->statusEnum()?->color() ?? 'secondary'
        );
    }

    protected function pixCode(): Attribute
    {
        return Attribute::get(fn () =>
            data_get($this->provider_payload, 'provider_response.qr_code_text')
            ?? data_get($this->provider_payload, 'pix')
            ?? data_get($this->provider_payload, 'qrcode')
            ?? data_get($this->provider_payload, 'qr_code_text')
        );
    }

    protected function pixExpire(): Attribute
    {
        return Attribute::get(function () {
            return data_get($this->provider_payload, 'expire')
                ?? data_get($this->provider_payload, 'data.expire')
                ?? null;
        });
    }

    protected function pixExpiresAt(): Attribute
    {
        return Attribute::get(function () {
            $created = data_get($this->provider_payload, 'created_at');
            $expire  = $this->pix_expire;
            if (!$created || !$expire) return null;
            return Carbon::parse($created)->addSeconds($expire);
        });
    }

    protected function pixExpired(): Attribute
    {
        return Attribute::get(fn () =>
            $this->pix_expires_at ? $this->pix_expires_at < now() : false
        );
    }

    // ================= MUTATORS =================
    public function setStatusAttribute($value): void
    {
        $this->attributes['status'] = TransactionStatus::fromLoose((string) $value)->value;
    }

    public function setDirectionAttribute($value): void
    {
        $value = strtolower($value);
        $this->attributes['direction'] = in_array($value, [self::DIR_IN, self::DIR_OUT])
            ? $value
            : self::DIR_IN;
    }

    public function setTxidAttribute($value): void
    {
        $v = preg_replace('/[^A-Za-z0-9\-\._]/', '', (string) $value) ?? null;
        $this->attributes['txid'] = $v ? substr($v, 0, 64) : null;
    }

    public function setProviderTransactionIdAttribute($value): void
    {
        $v = preg_replace('/[^A-Za-z0-9\-\._]/', '', (string) $value) ?? null;
        $this->attributes['provider_transaction_id'] = $v ? substr($v, 0, 100) : null;
    }

    public function setE2eIdAttribute($value): void
    {
        $v = preg_replace('/[^A-Za-z0-9]/', '', (string) $value) ?? null;
        $this->attributes['e2e_id'] = $v ? substr($v, 0, 100) : null;
    }

    // ================= HELPERS =================
    public function markPaid(?\DateTimeInterface $when = null): void
    {
        $this->status  = TransactionStatus::PAGA;
        $this->paid_at = $when ?? now();
        $this->save();
    }

    // ================= DEFAULTS =================
    protected static function booted(): void
    {
        static::creating(function (self $m) {
            $m->direction = $m->direction ?: self::DIR_IN;
            $m->currency  = $m->currency  ?: 'BRL';
            $m->method    = $m->method    ?: 'pix';
            $m->applied_available_amount = $m->applied_available_amount ?? 0;
            $m->applied_blocked_amount   = $m->applied_blocked_amount   ?? 0;
        });
    }
}
