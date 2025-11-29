<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// database/migrations/xxxx_xx_xx_xxxxxx_add_unique_index_to_transactions_txid.php
return new class extends \Illuminate\Database\Migrations\Migration {
    public function up(): void {
        \Illuminate\Support\Facades\Schema::table('transactions', function ($t) {
            if (!\Illuminate\Support\Facades\DB::getSchemaBuilder()->hasColumn('transactions', 'txid')) {
                // caso não exista, crie a coluna conforme seu padrão
                $t->string('txid', 64)->nullable()->index();
            }
            $t->unique('txid');
        });
    }
    public function down(): void {
        \Illuminate\Support\Facades\Schema::table('transactions', function ($t) {
            $t->dropUnique(['txid']);
        });
    }
};
