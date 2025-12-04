<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Normaliza valores inválidos antes do ENUM

        DB::table('transactions')
            ->where('status', 'falha')
            ->update(['status' => 'FAILED']);

        DB::table('transactions')
            ->where('status', 'paga')
            ->update(['status' => 'PAID']);

        DB::table('transactions')
            ->where('status', 'pendente')
            ->update(['status' => 'PENDING']);
    }

    public function down(): void
    {
        // Nada a desfazer
    }
};
