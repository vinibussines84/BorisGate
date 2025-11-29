<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1) Garante a coluna
        Schema::table('transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('transactions', 'external_id')) {
                $table->string('external_id', 191)->nullable()->after('provider_transaction_id');
            }
        });

        // 2) Tenta criar o índice único (compatível com SQLite)
        try {
            Schema::table('transactions', function (Blueprint $table) {
                // Em alguns bancos, tentar criar um índice já existente lança exceção
                $table->unique('external_id', 'transactions_external_id_unique');
            });
        } catch (\Throwable $e) {
            // Se já existir (ou SQLite não suportar re-criar), ignoramos
        }
    }

    public function down(): void
    {
        // Remove o índice único se existir (ignora erros em SQLite)
        try {
            Schema::table('transactions', function (Blueprint $table) {
                $table->dropUnique('transactions_external_id_unique');
            });
        } catch (\Throwable $e) {
            // ok
        }

        // (Opcional) Remover a coluna:
        // if (Schema::hasColumn('transactions', 'external_id')) {
        //     Schema::table('transactions', function (Blueprint $table) {
        //         $table->dropColumn('external_id');
        //     });
        // }
    }
};
