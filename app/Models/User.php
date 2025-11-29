<?php

namespace App\Models;

use App\Domain\Payments\Contracts\InboundPaymentsProvider;
use App\Domain\Payments\Contracts\OutboundPaymentsProvider;
use App\Models\Provider;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements FilamentUser
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Campos liberados para atribuição em massa.
     */
    protected $fillable = [
        'nome_completo',
        'data_nascimento',
        'email',
        'password',

        // 🔐 Documento
        'cpf_cnpj',

        // 💰 Saldos
        'amount_available',
        'amount_retained',
        'blocked_amount',

        // 📊 Status e gate
        'user_status',
        'dashrash',

        // 👑 Admin / RBAC
        'is_admin',
        'role',
        'permissions',

        // 🧾 Taxas
        'tax_in_enabled',
        'tax_in_mode',
        'tax_in_fixed',
        'tax_in_percent',
        'tax_out_enabled',
        'tax_out_mode',
        'tax_out_fixed',
        'tax_out_percent',

        // 🌐 Webhook
        'webhook_enabled',
        'webhook_in_url',
        'webhook_out_url',

        // 🔌 Providers por fluxo (FKs)
        'cashin_provider_id',
        'cashout_provider_id',

        // 📎 KYC (paths e status)
        'selfie_path',
        'doc_front_path',
        'doc_back_path',
        'kyc_status',

        // ✅ Auto-aprovação de saques
        'auto_approve_withdrawals',
    ];

    /**
     * Atributos ocultos.
     */
    protected $hidden = [
        'password',
        'remember_token',
        'secretkey',
        // 'authkey', // opcional esconder
    ];

    /**
     * Casts.
     */
    protected $casts = [
        'data_nascimento'   => 'date',
        'email_verified_at' => 'datetime',
        'password'          => 'hashed',

        // 💰 Saldos
        'amount_available'  => 'decimal:2',
        'amount_retained'   => 'decimal:2',
        'blocked_amount'    => 'decimal:2',

        // Controle
        'is_admin'          => 'boolean',
        'permissions'       => 'array',
        'dashrash'          => 'integer',

        // Taxas
        'tax_in_enabled'    => 'boolean',
        'tax_out_enabled'   => 'boolean',
        'tax_in_fixed'      => 'decimal:2',
        'tax_in_percent'    => 'decimal:2',
        'tax_out_fixed'     => 'decimal:2',
        'tax_out_percent'   => 'decimal:2',

        // Webhook
        'webhook_enabled'   => 'boolean',

        // 2FA (se existir a coluna)
        'google2fa_enabled' => 'boolean',

        // ✅ Auto-aprovação de saques (0/1 estrito)
        'auto_approve_withdrawals' => 'integer',
    ];

    /* -------------------------
     | 🔗 Relacionamentos
     ------------------------- */
    public function cashinProvider(): BelongsTo
    {
        return $this->belongsTo(Provider::class, 'cashin_provider_id');
    }

    public function cashoutProvider(): BelongsTo
    {
        return $this->belongsTo(Provider::class, 'cashout_provider_id');
    }

    /* -------------------------
     | 🔐 Helpers de chave
     ------------------------- */
    public static function generateHex(int $bytes): string
    {
        return bin2hex(random_bytes($bytes));
    }

    public static function generateUniqueAuthKey(): string
    {
        do {
            $key = self::generateHex(16);
            $exists = static::query()->where('authkey', $key)->exists();
        } while ($exists);

        return $key;
    }

    public static function generateSecretKey(): string
    {
        return self::generateHex(32);
    }

    /* -------------------------
     | ⚙️ Boot
     ------------------------- */
    protected static function booted(): void
    {
        static::creating(function (self $user) {
            // 💰 Saldos padrão
            $user->amount_available = $user->amount_available ?? 0;
            $user->amount_retained  = $user->amount_retained  ?? 0;
            $user->blocked_amount   = $user->blocked_amount  ?? 0;

            // 📊 Status/gate
            $user->user_status = $user->user_status ?? 'ativo';
            $user->dashrash    = $user->dashrash ?? 0;

            // 👑 RBAC
            $user->is_admin    = $user->is_admin    ?? false;
            $user->role        = $user->role        ?? null;
            $user->permissions = $user->permissions ?? [];

            // 🧾 Taxas padrão
            $user->tax_in_enabled   = $user->tax_in_enabled   ?? false;
            $user->tax_in_mode      = $user->tax_in_mode      ?? 'percentual';
            $user->tax_in_fixed     = $user->tax_in_fixed     ?? 0;
            $user->tax_in_percent   = $user->tax_in_percent   ?? 0;

            $user->tax_out_enabled  = $user->tax_out_enabled  ?? false;
            $user->tax_out_mode     = $user->tax_out_mode     ?? 'percentual';
            $user->tax_out_fixed    = $user->tax_out_fixed    ?? 0;
            $user->tax_out_percent  = $user->tax_out_percent  ?? 0;

            // 🌐 Webhook padrão
            $user->webhook_enabled  = $user->webhook_enabled ?? false;

            // ✅ Auto-aprovação padrão (0 = inativo)
            $user->auto_approve_withdrawals = (int) ($user->auto_approve_withdrawals ?? 0);

            // 🔑 Geração de chaves (se colunas existirem)
            if (empty($user->authkey))   $user->authkey   = self::generateUniqueAuthKey();
            if (empty($user->secretkey)) $user->secretkey = self::generateSecretKey();
        });

        static::saving(function (self $user) {
            if (is_null($user->permissions)) {
                $user->permissions = [];
            }

            // normaliza explicitamente para 0/1
            $user->auto_approve_withdrawals = (int) ($user->auto_approve_withdrawals ?? 0);
        });
    }

    /* -------------------------
     | 🔁 Rotacionar secret key
     ------------------------- */
    public function rotateSecretKey(): void
    {
        $this->secretkey = self::generateSecretKey();
        $this->save();
    }

    /* -------------------------
     | 🤝 Services (IN/OUT)
     ------------------------- */
    public function cashinService(): ?InboundPaymentsProvider
    {
        $p = $this->cashinProvider;
        if (!$p || !$p->active) return null;

        $service = app()->makeWith($p->service_class, ['config' => $p->config ?? []]);
        return $service instanceof InboundPaymentsProvider ? $service : null;
    }

    public function cashoutService(): ?OutboundPaymentsProvider
    {
        $p = $this->cashoutProvider;
        if (!$p || !$p->active) return null;

        $service = app()->makeWith($p->service_class, ['config' => $p->config ?? []]);
        return $service instanceof OutboundPaymentsProvider ? $service : null;
    }

    /* -------------------------
     | 🧭 Atalhos Provider por ref
     ------------------------- */
    public function setCashinProviderByRef(?string $ref): void
    {
        $prov = Provider::findInboundByRef($ref);
        $this->cashin_provider_id = $prov?->id;
    }

    public function setCashoutProviderByRef(?string $ref): void
    {
        $prov = Provider::findOutboundByRef($ref);
        $this->cashout_provider_id = $prov?->id;
    }

    /* -------------------------
     | 🎛️ Filament access
     ------------------------- */
    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() === 'xota') {
            return ((int) ($this->dashrash ?? 0) === 1) && ($this->user_status === 'ativo');
        }
        return true;
    }

    public function getFilamentName(): string
    {
        $nome = trim((string) ($this->nome_completo ?? ''));
        return $nome !== '' ? $nome : ((string) ($this->email ?? 'Usuário'));
    }

    /* -------------------------
     | 🧩 Compatibilidade
     ------------------------- */
    public function getNameAttribute(): ?string
    {
        return $this->nome_completo;
    }

    /* -------------------------
     | 🧼 Mutators / Accessors
     ------------------------- */
    public function setCpfCnpjAttribute($value): void
    {
        // sempre persistir só dígitos
        $this->attributes['cpf_cnpj'] = preg_replace('/\D+/', '', (string) $value);
    }

    public function getCpfCnpjMaskedAttribute(): ?string
    {
        $doc = preg_replace('/\D+/', '', (string) ($this->attributes['cpf_cnpj'] ?? ''));
        if ($doc === '') return null;

        if (strlen($doc) === 11) {
            return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $doc);
        }
        if (strlen($doc) === 14) {
            return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $doc);
        }
        return $doc; // fallback
    }

    /* -------------------------
     | 💰 Labels de taxas
     ------------------------- */
    public function getTaxInLabelAttribute(): string
    {
        if (!$this->tax_in_enabled) return 'Desativado';

        return $this->tax_in_mode === 'fixo'
            ? 'Fixo: R$ ' . number_format((float) $this->tax_in_fixed, 2, ',', '.')
            : 'Percentual: ' . number_format((float) $this->tax_in_percent, 2, ',', '.') . '%';
    }

    public function getTaxOutLabelAttribute(): string
    {
        if (!$this->tax_out_enabled) return 'Desativado';

        return $this->tax_out_mode === 'fixo'
            ? 'Fixo: R$ ' . number_format((float) $this->tax_out_fixed, 2, ',', '.')
            : 'Percentual: ' . number_format((float) $this->tax_out_percent, 2, ',', '.') . '%';
    }

    /* -------------------------
     | 🔎 Scopes
     ------------------------- */
    public function scopeAtivos($query)
    {
        return $query->where('user_status', 'ativo');
    }

    public function scopeComTaxaIn($query)
    {
        return $query->where('tax_in_enabled', true);
    }

    public function scopeComTaxaOut($query)
    {
        return $query->where('tax_out_enabled', true);
    }

    /* -------------------------
     | ✅ Helper de auto-aprovação
     ------------------------- */
    public function isAutoApproveActive(): bool
    {
        return (int) ($this->auto_approve_withdrawals ?? 0) === 1;
    }
}
