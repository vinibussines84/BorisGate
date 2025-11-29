<?php

// database/migrations/xxxx_xx_xx_xxxxxx_add_fee_and_gross_to_withdraws_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('withdraws', function (Blueprint $table) {
            $table->decimal('fee_amount', 12, 2)->nullable()->after('amount');
            $table->decimal('gross_amount', 12, 2)->nullable()->after('fee_amount');
        });
    }
    public function down(): void {
        Schema::table('withdraws', function (Blueprint $table) {
            $table->dropColumn(['fee_amount', 'gross_amount']);
        });
    }
};
