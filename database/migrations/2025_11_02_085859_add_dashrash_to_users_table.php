<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // tinyInteger é suficiente (0/1/2). Default 0 = sem acesso
            $table->tinyInteger('dashrash')
                ->default(0)
                ->comment('1 = pode acessar painel; 0/2/outros = bloqueado')
                ->after('user_status');

            // Opcional: índice para filtros em painel/listagens
            $table->index('dashrash');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['dashrash']); // remove o index
            $table->dropColumn('dashrash');  // remove a coluna
        });
    }
};
