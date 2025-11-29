<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('withdraws', function (Blueprint $table) {
            // Se você tiver tabela tenants, use foreignId com FK:
            // $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();

            // Caso não tenha tabela tenants, use apenas a coluna + índice:
            $table->unsignedBigInteger('tenant_id')->nullable()->after('id');
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::table('withdraws', function (Blueprint $table) {
            $table->dropIndex(['tenant_id']);
            $table->dropColumn('tenant_id');
        });
    }
};
