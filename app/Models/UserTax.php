<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserTax extends Model
{
    protected $fillable = [
        'user_id',
        'tax_in_enabled','tax_in_mode','tax_in_fixed','tax_in_percent',
        'tax_out_enabled','tax_out_mode','tax_out_fixed','tax_out_percent',
    ];

    protected $casts = [
        'tax_in_enabled'  => 'bool',
        'tax_out_enabled' => 'bool',
        'tax_in_fixed'    => 'decimal:2',
        'tax_in_percent'  => 'decimal:2',
        'tax_out_fixed'   => 'decimal:2',
        'tax_out_percent' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Labels auxiliares (úteis na tabela)
    public function getTaxInLabelAttribute(): string
    {
        if (! $this->tax_in_enabled) return 'Desativado';
        return $this->tax_in_mode === 'fixo'
            ? 'Fixo: R$ '.number_format((float)$this->tax_in_fixed, 2, ',', '.')
            : 'Percentual: '.number_format((float)$this->tax_in_percent, 2, ',', '.').'%';
    }

    public function getTaxOutLabelAttribute(): string
    {
        if (! $this->tax_out_enabled) return 'Desativado';
        return $this->tax_out_mode === 'fixo'
            ? 'Fixo: R$ '.number_format((float)$this->tax_out_fixed, 2, ',', '.')
            : 'Percentual: '.number_format((float)$this->tax_out_percent, 2, ',', '.').'%';
    }
}
