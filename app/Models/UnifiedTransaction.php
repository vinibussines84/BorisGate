<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class UnifiedTransaction extends Model
{
    protected $table = 'transactions'; // apenas para o Eloquent não reclamar

    protected $guarded = [];
    public $timestamps = false;
    public $incrementing = false;

    protected static function booted()
    {
        static::addGlobalScope('unified', function (Builder $query) {

            // ENTRADAS
            $transactions = DB::table('transactions')
                ->selectRaw("
                    id,
                    tenant_id,
                    user_id,
                    amount,
                    fee,
                    amount - fee as net_amount,
                    method,
                    provider,
                    txid,
                    e2e_id,
                    status,
                    created_at,
                    paid_at,
                    external_reference,
                    provider_transaction_id,
                    'in' as direction,
                    description
                ");

            // SAÍDAS
            $withdraws = DB::table('withdraws')
                ->selectRaw("
                    id,
                    tenant_id,
                    user_id,
                    amount as amount,
                    fee_amount as fee,
                    gross_amount - fee_amount as net_amount,
                    'pix' as method,
                    provider,
                    pixkey as txid,
                    null as e2e_id,
                    status,
                    created_at,
                    processed_at as paid_at,
                    idempotency_key as external_reference,
                    null as provider_transaction_id,
                    'out' as direction,
                    description
                ");

            // 🔥 Unificação final
            $query->fromSub(
                $transactions->unionAll($withdraws),
                'unified_transactions'
            );
        });
    }

    /** Relacionamento necessário no Filament */
    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    /** Impede gravação em tabela virtual */
    public function save(array $options = [])
    {
        return false;
    }
}
