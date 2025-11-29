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
        'tenant_id','user_id','amount','fee',
        // 'net_amount', // ❌ coluna gerada no MySQL — não deve ser preenchida manualmente
        'direction','status','currency','method',
        'provider','provider_transaction_id','external_id','external_reference',
        'txid','e2e_id','provider_payload','description','authorized_at','paid_at',
        'refunded_at','canceled_at','idempotency_key','ip','user_agent',
        'applied_available_amount','applied_blocked_amount',
    ];

    /** ✅ Casts */
    protected $casts = [
        'amount'           => 'decimal:2',
        'fee'              => 'decimal:2',
        'net_amount'       => 'decimal:2', // ok manter cast para leitura (coluna gerada)
        'provider_payload' => 'array',
        'authorized_at'    => 'datetime',
        'paid_at'          => 'datetime',
        'refunded_at'      => 'datetime',
        'canceled_at'      => 'datetime',
        // ⚠️ IMPORTANTE: NÃO CASTAR MAIS PRA ENUM, SENÃO DÁ ERRO COM "pending"
        // 'status'           => TransactionStatus::class,
        'applied_available_amount' => 'decimal:2',
        'applied_blocked_amount'   => 'decimal:2',
    ];

    protected $appends = [
        'status_label','status_color','pix_code','pix_expire','pix_expires_at','pix_expired',
    ];

    // ================= Relações =================
    public function tenant()
    {
        return $this->belongsTo(\App\Models\Tenant::class);
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    // ================= Helper interno =================
    protected function statusEnum(): ?TransactionStatus
    {
        $raw = $this->attributes['status'] ?? null;
        if ($raw === null) {
            return null;
        }

        // se vier "pending", "paid", etc, normaliza aqui
        return TransactionStatus::fromLoose((string) $raw);
    }

    // ================= Scopes =================
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

    /** 🔎 Identificador interno: transactions oriundas de cobrança */
    public function scopeDeCobranca($q)
    {
        return $q->where('external_reference', 'like', 'cobranca:%');
    }

    // ================= Accessors =================
    protected function statusLabel(): Attribute
    {
        return Attribute::get(function () {
            return $this->statusEnum()?->label() ?? '—';
        });
    }

    protected function statusColor(): Attribute
    {
        return Attribute::get(function () {
            return $this->statusEnum()?->color() ?? 'secondary';
        });
    }

    protected function pixCode(): Attribute
    {
        return Attribute::get(fn () =>
            // ✅ formato vindo do seu provider (ex.: Veltrax/Ecomovi)
            data_get($this->provider_payload, 'provider_response.qr_code_text')
            // formatos alternativos já usados no seu código
            ?? data_get($this->provider_payload, 'data.pix')
            ?? data_get($this->provider_payload, 'response.data.pix')
            ?? data_get($this->provider_payload, 'pix')
        );
    }

    protected function pixExpire(): Attribute
    {
        return Attribute::get(function () {
            $v = data_get($this->provider_payload, 'data.expire')
              ?? data_get($this->provider_payload, 'response.data.expire')
              ?? data_get($this->provider_payload, 'expire');
            return is_null($v) ? null : (int) $v;
        });
    }

    protected function pixExpiresAt(): Attribute
    {
        return Attribute::get(function () {
            $createdRaw = data_get($this->provider_payload, 'data.createdAt')
                       ?? data_get($this->provider_payload, 'response.data.createdAt');
            $expire     = $this->pix_expire;
            if (!$createdRaw || !$expire) return null;
            try {
                $created = Carbon::parse($createdRaw);
                return $created->copy()->addSeconds((int) $expire);
            } catch (\Throwable) {
                return null;
            }
        });
    }

    protected function pixExpired(): Attribute
    {
        return Attribute::get(function () {
            $expiresAt = $this->pix_expires_at;
            if (!$expiresAt) return false;
            $nowUtc = now('America/Sao_Paulo')->utc();
            return $expiresAt instanceof Carbon
                ? $expiresAt->lessThan($nowUtc)
                : false;
        });
    }

    // ================= Mutators / Normalizações =================
    /**
     * ✅ Mantemos um mutator leve só para quando alguém setar string “solta”.
     * - Se vier enum, respeita.
     * - Se vier string (ex: 'paid', 'paga'), normaliza via fromLoose().
     */
    public function setStatusAttribute($value): void
    {
        if ($value instanceof TransactionStatus) {
            $this->attributes['status'] = $value->value;
            return;
        }

        $this->attributes['status'] = TransactionStatus::fromLoose((string) $value)->value;
    }

    public function setDirectionAttribute($value): void
    {
        $dir = strtolower((string) $value);
        $this->attributes['direction'] = in_array($dir, [self::DIR_IN, self::DIR_OUT], true)
            ? $dir
            : self::DIR_IN;
    }

    public function setMethodAttribute($value): void
    {
        $this->attributes['method'] = strtolower((string) $value);
    }

    public function setCurrencyAttribute($value): void
    {
        $cur = strtoupper((string) $value);
        $this->attributes['currency'] = $cur !== '' ? $cur : 'BRL';
    }

    /** 🔐 Sanitize + limite seguro para txid (até 64) */
    public function setTxidAttribute($value): void
    {
        $v = is_scalar($value) ? (string) $value : '';
        // mantém letras, números e separadores comuns (evita estourar e guarda estável)
        $v = preg_replace('/[^A-Za-z0-9\-\._]/', '', $v) ?? '';
        $this->attributes['txid'] = $v !== '' ? substr($v, 0, 64) : null;
    }

    /** 🔐 Sanitize + limite seguro para provider_transaction_id (até 100) */
    public function setProviderTransactionIdAttribute($value): void
    {
        $v = is_scalar($value) ? (string) $value : '';
        $v = preg_replace('/[^A-Za-z0-9\-\._]/', '', $v) ?? '';
        $this->attributes['provider_transaction_id'] = $v !== '' ? substr($v, 0, 100) : null;
    }

    // ================= Atalhos =================
    public function isPaga(): bool
    {
        return $this->statusEnum() === TransactionStatus::PAGA;
    }

    public function isPendente(): bool
    {
        return $this->statusEnum() === TransactionStatus::PENDENTE;
    }

    public function isFalha(): bool
    {
        return $this->statusEnum() === TransactionStatus::FALHA;
    }

    public function isErro(): bool
    {
        return $this->statusEnum() === TransactionStatus::ERRO;
    }

    public function isMed(): bool
    {
        return $this->statusEnum() === TransactionStatus::MED;
    }

    public function isDeCobranca(): bool
    {
        return str_starts_with((string) $this->external_reference, 'cobranca:');
    }

    /** 🔧 Helper opcional pra usar no webhook/controladores */
    public function markPaid(?\DateTimeInterface $when = null): void
    {
        $this->status  = TransactionStatus::PAGA; // passa pelo mutator, salva 'paga'
        $this->paid_at = $when ?? now();
        $this->save();
    }

    // ================= Defaults =================
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
