<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Se existirem colunas antigas de provider simples, você pode removê-las aqui (opcional):
            // if (Schema::hasColumn('users', 'provider_id'))      $table->dropConstrainedForeignId('provider_id');
            // if (Schema::hasColumn('users', 'provider_in'))      $table->dropColumn('provider_in');
            // if (Schema::hasColumn('users', 'provider_out'))     $table->dropColumn('provider_out');
            // if (Schema::hasColumn('users', 'provider_credentials')) $table->dropColumn('provider_credentials');

            $table->foreignId('cashin_provider_id')->nullable()->after('blocked_amount')
                ->constrained('providers')->nullOnDelete();

            $table->foreignId('cashout_provider_id')->nullable()->after('cashin_provider_id')
                ->constrained('providers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cashin_provider_id');
            $table->dropConstrainedForeignId('cashout_provider_id');
        });
    }
};
