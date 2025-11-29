<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class Withdraw extends Model
{
    // use SoftDeletes;

    /** Tabela correta (plural irregular) */
    protected $table = 'withdraws';

    /** Status padronizados para evitar typos */
    public const STATUS_PENDING    = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_PAID       = 'paid';
    public const STATUS_FAILED     = 'failed';
    public const STATUS_CANCELED   = 'canceled';
    // Observação: se quiser usar "error" no banco, inclua no ENUM ou troque a coluna para VARCHAR.
    // No código, trate erros transientes como FAILED para compatibilidade com o schema atual.

    /**
     * amount        = líquido
     * gross_amount  = bruto
     * fee_amount    = taxa
     */
    protected $fillable = [
        'user_id',
        'tenant_id',
        'currency',
        'provider',

        'amount',
        'gross_amount',
        'fee_amount',

        'description',
        'pixkey',
        'pixkey_type',

        'idempotency_key',
        'pin_encrypted',

        'status',
        'processed_at',
        'canceled_at',

        'meta',
        // 'created_source',
    ];

    protected $casts = [
        'amount'        => 'float',
        'gross_amount'  => 'float',
        'fee_amount'    => 'float',

        'processed_at'  => 'datetime',
        'canceled_at'   => 'datetime',
        'created_at'    => 'datetime',
        'updated_at'    => 'datetime',

        'meta'          => 'array',
    ];

    protected $hidden = [
        'pin_encrypted',
    ];

    protected $appends = [
        'pixkey_masked',
        'status_label',
        'status_color',
        'origin',
    ];

    /** Blindagem: remove atributos que não existem na tabela ao salvar */
    protected static function booted(): void
    {
        $stripUnknown = function (Withdraw $m) {
            static $colsCache = [];
            $table = $m->getTable();

            if (!isset($colsCache[$table])) {
                $colsCache[$table] = Schema::hasTable($table)
                    ? Schema::getColumnListing($table)
                    : [];
            }
            $cols = $colsCache[$table];

            foreach (array_keys($m->attributes) as $key) {
                if (!in_array($key, $cols, true)) {
                    unset($m->attributes[$key]);
                }
            }
        };

        static::creating($stripUnknown);
        static::updating($stripUnknown);
        static::saving($stripUnknown);
    }

    /* =======================================
     |  Relacionamentos
     =======================================*/
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /* =======================================
     |  Accessors / Helpers
     =======================================*/

    /** Origem deduzida */
    public function getOriginAttribute(): string
    {
        $explicit = $this->getAttribute('created_source');
        if (!is_null($explicit)) {
            $v = strtolower((string) $explicit);
            return in_array($v, ['api', 'painel', 'panel', 'dashboard'], true)
                ? ($v === 'api' ? 'API' : 'Painel')
                : 'Painel';
        }

        $flagApi = Schema::hasColumn($this->getTable(), 'meta')
            ? (bool) data_get($this->meta ?? [], 'api_request', false)
            : false;

        if (!empty($this->getAttribute('idempotency_key')) || $flagApi) {
            return 'API';
        }

        return 'Painel';
    }

    /** Máscara amigável */
    public function getPixkeyMaskedAttribute(): string
    {
        $v = (string) ($this->attributes['pixkey'] ?? '');
        if ($v === '') return '••••••';

        $type = $this->attributes['pixkey_type'] ?? null;

        return match ($type) {
            'cpf'   => substr($v, 0, 3) . '.***.***-' . substr($v, -2),
            'cnpj'  => substr($v, 0, 2) . '.***.***/****-' . substr($v, -2),
            'email' => (function ($e) {
                [$u, $d] = array_pad(explode('@', $e, 2), 2, '');
                $u2 = strlen($u) > 2 ? substr($u, 0, 2) . '***' : $u . '***';
                return $u2 . '@' . $d;
            })($v),
            'phone' => '(**) *****-' . substr(preg_replace('/\D+/', '', $v), -4),
            'evp'   => substr($v, 0, 4) . '****' . substr($v, -4),
            default => strlen($v) > 6
                ? substr($v, 0, 3) . str_repeat('•', max(strlen($v) - 6, 0)) . substr($v, -3)
                : $v,
        };
    }

    /** Label de status */
    public function getStatusLabelAttribute(): string
    {
        return match ((string) ($this->attributes['status'] ?? '')) {
            self::STATUS_PAID       => 'Pago',
            self::STATUS_PENDING    => 'Pendente',
            self::STATUS_FAILED     => 'Falha',
            self::STATUS_PROCESSING => 'Processando',
            self::STATUS_CANCELED   => 'Cancelado',
            default                 => ucfirst((string) ($this->attributes['status'] ?? '—')),
        };
    }

    /** Cor do status */
    public function getStatusColorAttribute(): string
    {
        return match ((string) ($this->attributes['status'] ?? '')) {
            self::STATUS_PAID       => 'success',
            self::STATUS_PENDING    => 'warning',
            self::STATUS_FAILED     => 'danger',
            self::STATUS_PROCESSING => 'info',
            self::STATUS_CANCELED   => 'gray',
            default                 => 'secondary',
        };
    }

    /** Valor bruto para estorno (fallback em amount + fee) */
    public function getRefundGrossAttribute(): ?float
    {
        $gross = $this->getAttribute('gross_amount');
        if (!is_null($gross)) return (float) $gross;

        $amount = $this->getAttribute('amount');
        $fee    = $this->getAttribute('fee_amount');

        if (!is_null($amount) && !is_null($fee)) {
            return round(((float) $amount) + (float) $fee, 2);
        }

        return null;
    }

    /* =======================================
     |  Scopes
     =======================================*/
    public function scopeOwnedBy($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeStatus($query, ?string $status)
    {
        return $status ? $query->where('status', $status) : $query;
    }

    public function scopeSearch($query, ?string $term)
    {
        if (!$term) return $query;

        $s = "%{$term}%";
        return $query->where(function ($q) use ($s) {
            $q->where('description', 'like', $s)
              ->orWhere('pixkey', 'like', $s)
              ->orWhere('idempotency_key', 'like', $s);
        });
    }

    /** Itens criados via API (idempotency_key ou meta->api_request=true) */
    public function scopeFromApi($q)
    {
        $table = $q->getModel()->getTable();

        return $q->whereNotNull("{$table}.idempotency_key")
                 ->orWhere(function ($w) use ($table) {
                     if (Schema::hasColumn($table, 'meta')) {
                         $w->where("{$table}.meta->api_request", true);
                     }
                 });
    }

    /** Itens criados pelo painel (sem idempotency_key e meta->api_request=false/null) */
    public function scopeFromPainel($q)
    {
        $table = $q->getModel()->getTable();

        return $q->whereNull("{$table}.idempotency_key")
                 ->where(function ($w) use ($table) {
                     if (Schema::hasColumn($table, 'meta')) {
                         $w->whereNull("{$table}.meta->api_request")
                           ->orWhere("{$table}.meta->api_request", false);
                     }
                 });
    }
}
