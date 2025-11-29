<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Só cria se não existir (evita erro em reexecuções)
            if (! Schema::hasColumn('users', 'authkey')) {
                $table->char('authkey', 32)->nullable()->unique()->after('permissions');
            }
            if (! Schema::hasColumn('users', 'secretkey')) {
                $table->char('secretkey', 64)->nullable()->after('authkey');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Remover UNIQUE precisa do nome do índice:
            if (Schema::hasColumn('users', 'authkey')) {
                // nome padrão: {tabela}_{coluna}_unique
                $table->dropUnique('users_authkey_unique');
            }

            // Remover colunas se existirem
            $columnsToDrop = [];
            if (Schema::hasColumn('users', 'authkey')) {
                $columnsToDrop[] = 'authkey';
            }
            if (Schema::hasColumn('users', 'secretkey')) {
                $columnsToDrop[] = 'secretkey';
            }
            if (! empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
