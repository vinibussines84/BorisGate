<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // garanta que seja string e com default
            $table->string('status', 20)->default('pendente')->index()->change();
        });

        // Opcional: constraint CHECK (MySQL 8.0.16+ / Postgres)
        // Ajuste se seu banco suportar/permitir alterar check dinamicamente
        try {
            $driver = DB::getDriverName();
            if ($driver === 'mysql') {
                DB::statement("ALTER TABLE transactions
                    ADD CONSTRAINT chk_transactions_status
                    CHECK (status IN ('falha','erro','paga','pendente','med'))");
            } elseif ($driver === 'pgsql') {
                DB::statement("ALTER TABLE transactions
                    ADD CONSTRAINT chk_transactions_status
                    CHECK (status IN ('falha','erro','paga','pendente','med'))");
            }
        } catch (\Throwable $e) {
            // Se seu provedor não suporta CHECK, ignore sem quebrar deploy
        }
    }

    public function down(): void
    {
        // Remover CHECK se existir
        try {
            $driver = DB::getDriverName();
            if ($driver === 'mysql') {
                DB::statement("ALTER TABLE transactions DROP CONSTRAINT chk_transactions_status");
            } elseif ($driver === 'pgsql') {
                DB::statement("ALTER TABLE transactions DROP CONSTRAINT chk_transactions_status");
            }
        } catch (\Throwable $e) {}

        Schema::table('transactions', function (Blueprint $table) {
            // volte ao estado anterior conforme seu histórico
            $table->string('status', 50)->nullable()->change();
        });
    }
};
