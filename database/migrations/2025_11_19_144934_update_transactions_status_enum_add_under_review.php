<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('transactions')) {
            return;
        }

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            // 🔹 MySQL — recria o constraint com o novo status
            try {
                DB::statement('ALTER TABLE transactions DROP CHECK chk_transactions_status;');
            } catch (\Throwable $e) {
                // pode não existir ainda — ignora
            }

            DB::statement("
                ALTER TABLE transactions
                ADD CONSTRAINT chk_transactions_status
                CHECK (status IN (
                    'falha',
                    'erro',
                    'paga',
                    'pendente',
                    'med',
                    'under_review'
                ));
            ");
        } elseif ($driver === 'sqlite') {
            // 🔹 SQLite — apenas loga a intenção (não suporta ALTER TABLE ADD CONSTRAINT)
            info('[Migration] SQLite detectado — ignorando ALTER TABLE CHECK constraint');
        } else {
            info("[Migration] Banco {$driver} não suportado para alteração de constraint.");
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('transactions')) {
            return;
        }

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            try {
                DB::statement('ALTER TABLE transactions DROP CHECK chk_transactions_status;');
            } catch (\Throwable $e) {
                // ignora se já não existir
            }

            DB::statement("
                ALTER TABLE transactions
                ADD CONSTRAINT chk_transactions_status
                CHECK (status IN (
                    'falha',
                    'erro',
                    'paga',
                    'pendente',
                    'med'
                ));
            ");
        } elseif ($driver === 'sqlite') {
            info('[Migration rollback] SQLite detectado — ignorando ALTER TABLE CHECK constraint');
        }
    }
};
