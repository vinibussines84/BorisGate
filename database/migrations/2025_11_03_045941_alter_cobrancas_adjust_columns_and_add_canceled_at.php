<?php
// database/migrations/2025_11_03_045941_alter_cobrancas_adjust_columns_and_add_canceled_at.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1) Adiciona canceled_at se não existir
        if (!Schema::hasColumn('cobrancas', 'canceled_at')) {
            Schema::table('cobrancas', function (Blueprint $table) {
                $table->timestamp('canceled_at')->nullable()->after('paid_at');
            });
        }

        // 2) Ajustes de colunas (use DBAL, mas evite no SQLite)
        $driver = config('database.default');
        if ($driver !== 'sqlite') {
            Schema::table('cobrancas', function (Blueprint $table) {
                // Ajuste de precisão do amount, se quiser padronizar em 14,2
                // (requer doctrine/dbal em "require")
                $table->decimal('amount', 14, 2)->change();

                // Se PRECISAR alterar tamanhos (apenas exemplo; remova se não precisar):
                // $table->string('provider', 60)->nullable(false)->change();
                // $table->string('external_id', 120)->nullable(false)->change();
                // $table->string('provider_transaction_id', 120)->nullable()->change();
            });
        }

        // 3) NÃO recrie índices já existentes.
        // Se, por alguma razão, você realmente precisar garantir o índice em "provider",
        // faça um check manual por driver e só crie se não existir.
        // Ex.: para SQLite (opcional, com cuidado):
        /*
        if ($driver === 'sqlite') {
            $exists = DB::scalar(
                "SELECT COUNT(*) FROM sqlite_master WHERE type='index' AND name = 'cobrancas_provider_index'"
            );
            if (!$exists) {
                DB::statement('CREATE INDEX "cobrancas_provider_index" ON "cobrancas" ("provider")');
            }
        }
        */
    }

    public function down(): void
    {
        // Reverte apenas o que adicionamos/alteramos aqui
        if (Schema::hasColumn('cobrancas', 'canceled_at')) {
            Schema::table('cobrancas', function (Blueprint $table) {
                $table->dropColumn('canceled_at');
            });
        }

        // Reverter mudanças de coluna só se não for SQLite
        $driver = config('database.default');
        if ($driver !== 'sqlite') {
            Schema::table('cobrancas', function (Blueprint $table) {
                // Voltar amount para 12,2 se era o valor anterior (ajuste conforme sua create)
                // $table->decimal('amount', 12, 2)->change();
            });
        }
    }
};
