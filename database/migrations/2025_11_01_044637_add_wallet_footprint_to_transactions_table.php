<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Rastro do que JÁ foi aplicado na carteira por ESTA transação
            $table->decimal('applied_available_amount', 18, 2)->default(0)->after('fee');
            $table->decimal('applied_blocked_amount', 18, 2)->default(0)->after('applied_available_amount');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['applied_available_amount', 'applied_blocked_amount']);
        });
    }
};
