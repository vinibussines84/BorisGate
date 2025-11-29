<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // 1) Adiciona colunas novas
        Schema::table('transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('transactions', 'txid_tmp')) {
                $table->string('txid_tmp', 64)->nullable()->after('e2e_id');
            }
            if (!Schema::hasColumn('transactions', 'provider_transaction_id_tmp')) {
                $table->string('provider_transaction_id_tmp', 100)->nullable()->after('txid_tmp');
            }
        });

        // 2) Copia os valores existentes
        DB::statement('UPDATE transactions SET txid_tmp = txid');
        DB::statement('UPDATE transactions SET provider_transaction_id_tmp = provider_transaction_id');

        // Se estiver rodando em SQLite, paramos aqui — NÃO remover colunas
        if (DB::getDriverName() === 'sqlite') {
            // Apenas cria índices nas novas colunas
            Schema::table('transactions', function (Blueprint $table) {
                $table->index('txid_tmp');
                $table->index('provider_transaction_id_tmp');
            });

            return;
        }

        // ==== A PARTIR DAQUI SOMENTE PARA MYSQL / POSTGRES ====

        // 3) Remove índices antigos
        try { Schema::table('transactions', fn (Blueprint $t) => $t->dropIndex(['txid'])); } catch (\Throwable $e) {}
        try { Schema::table('transactions', fn (Blueprint $t) => $t->dropUnique(['txid'])); } catch (\Throwable $e) {}
        try { Schema::table('transactions', fn (Blueprint $t) => $t->dropIndex(['provider_transaction_id'])); } catch (\Throwable $e) {}
        try { Schema::table('transactions', fn (Blueprint $t) => $t->dropUnique(['provider_transaction_id'])); } catch (\Throwable $e) {}

        // 4) Remove colunas antigas (MySQL/Postgres suportam)
        Schema::table('transactions', function (Blueprint $table) {
            if (Schema::hasColumn('transactions', 'txid')) {
                $table->dropColumn('txid');
            }
            if (Schema::hasColumn('transactions', 'provider_transaction_id')) {
                $table->dropColumn('provider_transaction_id');
            }
        });

        // 5) Renomeia temporárias para finais
        Schema::table('transactions', function (Blueprint $table) {
            $table->renameColumn('txid_tmp', 'txid');
            $table->renameColumn('provider_transaction_id_tmp', 'provider_transaction_id');
        });

        // 6) Cria índices (recomendado: index, não unique, para PIX)
        Schema::table('transactions', function (Blueprint $table) {
            $table->index('txid');
            $table->index('provider_transaction_id');
        });
    }

    public function down(): void
    {
        // rollback simplificado (somente se necessário)
    }
};
