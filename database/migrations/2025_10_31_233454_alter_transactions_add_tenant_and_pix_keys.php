<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1) Adicionar colunas (sem UNIQUE ainda)
        Schema::table('transactions', function (Blueprint $table) {
            // tenant_id (FK se existir tabela tenants; senão, unsignedBigInteger)
            if (!Schema::hasColumn('transactions', 'tenant_id')) {
                if (Schema::hasTable('tenants')) {
                    $table->foreignId('tenant_id')
                        ->nullable()
                        ->after('id')
                        ->constrained('tenants')
                        ->nullOnDelete()
                        ->index(); // transactions_tenant_id_index
                } else {
                    $table->unsignedBigInteger('tenant_id')
                        ->nullable()
                        ->after('id')
                        ->index(); // transactions_tenant_id_index
                }
            }

            // PIX keys
            if (!Schema::hasColumn('transactions', 'txid')) {
                $table->string('txid', 35)
                    ->nullable()
                    ->after('external_reference')
                    ->comment('PIX txid (até 35 chars)');
            }
            if (!Schema::hasColumn('transactions', 'e2e_id')) {
                $table->string('e2e_id', 100)
                    ->nullable()
                    ->after('txid')
                    ->comment('PIX EndToEndId');
            }
        });

        // 2) Criar UNIQUEs (nomes explícitos e estáveis)
        Schema::table('transactions', function (Blueprint $table) {
            $table->unique(['tenant_id','txid'],  'transactions_tenant_txid_unique');
            $table->unique(['tenant_id','e2e_id'], 'transactions_tenant_e2e_unique');
            // opcional:
            // $table->unique(['tenant_id','external_reference','method','direction'], 'transactions_tenant_extref_meth_dir_unique');
        });
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName(); // 'sqlite', 'mysql', etc.

        // 1) Remover UNIQUEs primeiro (tratando SQLite com IF EXISTS)
        if ($driver === 'sqlite') {
            // SQLite fica mais feliz com SQL direto
            DB::statement('DROP INDEX IF EXISTS "transactions_tenant_txid_unique"');
            DB::statement('DROP INDEX IF EXISTS "transactions_tenant_e2e_unique"');
            // DB::statement('DROP INDEX IF EXISTS "transactions_tenant_extref_meth_dir_unique"');
        } else {
            Schema::table('transactions', function (Blueprint $table) {
                try { $table->dropUnique('transactions_tenant_txid_unique'); } catch (\Throwable $e) {}
                try { $table->dropUnique('transactions_tenant_e2e_unique'); } catch (\Throwable $e) {}
                // try { $table->dropUnique('transactions_tenant_extref_meth_dir_unique'); } catch (\Throwable $e) {}
            });
        }

        // 2) Remover índice simples de tenant_id (se existir)
        if ($driver === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS "transactions_tenant_id_index"');
        } else {
            Schema::table('transactions', function (Blueprint $table) {
                try { $table->dropIndex('transactions_tenant_id_index'); } catch (\Throwable $e) {}
            });
        }

        // 3) Dropar colunas dependentes (e2e_id, txid)
        Schema::table('transactions', function (Blueprint $table) {
            if (Schema::hasColumn('transactions', 'e2e_id')) {
                try { $table->dropColumn('e2e_id'); } catch (\Throwable $e) {}
            }
            if (Schema::hasColumn('transactions', 'txid')) {
                try { $table->dropColumn('txid'); } catch (\Throwable $e) {}
            }
        });

        // 4) Dropar tenant_id por último (lidando com FK vs coluna simples)
        Schema::table('transactions', function (Blueprint $table) {
            if (Schema::hasColumn('transactions', 'tenant_id')) {
                try {
                    // se foi criado como foreignId(...)->constrained()
                    $table->dropConstrainedForeignId('tenant_id');
                } catch (\Throwable $e) {
                    try { $table->dropForeign(['tenant_id']); } catch (\Throwable $e2) {}
                    try { $table->dropColumn('tenant_id'); } catch (\Throwable $e3) {}
                }
            }
        });
    }
};
