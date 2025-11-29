<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Cobranca extends Model
{
    use HasFactory;

    protected $table = 'cobrancas';

    protected $fillable = [
        'user_id',
        'amount',
        'status',
        'external_id',
        'provider',
        'provider_transaction_id',
        'qrcode',
        'payload',
        'payer',
        'paid_at',
        'canceled_at',
    ];

    protected $casts = [
        'amount'      => 'float',     // front recebe número de verdade
        'payload'     => 'array',
        'payer'       => 'array',
        'paid_at'     => 'datetime',
        'canceled_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
