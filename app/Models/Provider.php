<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int         $id
 * @property string      $name
 * @property string      $code                 // identificador estável (único)
 * @property string      $service_class        // classe adapter (ex.: App\Services\VeltraxPay\VeltraxPayInbound)
 * @property string|null $provider_in          // alias de entrada (ex.: #01VEL)
 * @property string|null $provider_out         // alias de saída (ex.: #02-vel)
 * @property array|null  $config               // credenciais e opções do provider
 * @property bool        $active
 */
class Provider extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'service_class',
        'provider_in',
        'provider_out',
        'config',
        'active',
    ];

    protected $casts = [
        'config' => 'array',
        'active' => 'boolean',
    ];

    /* ============================================================
     | Scopes de consulta
     * ============================================================
     */

    /** Apenas ativos */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /** Busca por code OU alias de entrada (provider_in) */
    public function scopeByCodeOrInboundRef($query, string $ref)
    {
        return $query->where(function ($q) use ($ref) {
            $q->where('code', $ref)->orWhere('provider_in', $ref);
        });
    }

    /** Busca por code OU alias de saída (provider_out) */
    public function scopeByCodeOrOutboundRef($query, string $ref)
    {
        return $query->where(function ($q) use ($ref) {
            $q->where('code', $ref)->orWhere('provider_out', $ref);
        });
    }

    /* ============================================================
     | Helpers estáticos (resolução por referência)
     * ============================================================
     */

    /** Resolve provider de ENTRADA por code OU alias */
    public static function findInboundByRef(?string $ref): ?self
    {
        if (!$ref) return null;

        return static::query()
            ->active()
            ->byCodeOrInboundRef($ref)
            ->first();
    }

    /** Resolve provider de SAÍDA por code OU alias */
    public static function findOutboundByRef(?string $ref): ?self
    {
        if (!$ref) return null;

        return static::query()
            ->active()
            ->byCodeOrOutboundRef($ref)
            ->first();
    }

    /* ============================================================
     | Helpers diversos
     * ============================================================
     */

    /** Lê uma chave do config com fallback */
    public function config(string $key, mixed $default = null): mixed
    {
        $cfg = $this->config ?? [];
        // suportar notação "a.b.c"
        $segments = explode('.', $key);
        $value = $cfg;
        foreach ($segments as $seg) {
            if (!is_array($value) || !array_key_exists($seg, $value)) {
                return $default;
            }
            $value = $value[$seg];
        }
        return $value;
    }

    /** Indica se este provider tem alias de entrada configurado */
    public function supportsInbound(): bool
    {
        return !empty($this->provider_in);
    }

    /** Indica se este provider tem alias de saída configurado */
    public function supportsOutbound(): bool
    {
        return !empty($this->provider_out);
    }

    /* ============================================================
     | Mutators simples para evitar espaços acidentais
     * ============================================================
     */

    public function setCodeAttribute($value): void
    {
        $this->attributes['code'] = is_string($value) ? trim($value) : $value;
    }

    public function setProviderInAttribute($value): void
    {
        $this->attributes['provider_in'] = is_string($value) ? trim($value) : $value;
    }

    public function setProviderOutAttribute($value): void
    {
        $this->attributes['provider_out'] = is_string($value) ? trim($value) : $value;
    }
}
