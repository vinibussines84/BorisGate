<?php

// database/migrations/xxxx_xx_xx_xxxxxx_add_fee_and_net_to_cobrancas_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('cobrancas', function (Blueprint $table) {
            // valores brutos e líquidos
            if (!Schema::hasColumn('cobrancas', 'fee_amount')) {
                $table->decimal('fee_amount', 14, 2)->nullable()->after('amount');
            }
            if (!Schema::hasColumn('cobrancas', 'net_amount')) {
                $table->decimal('net_amount', 14, 2)->nullable()->after('fee_amount');
            }
        });
    }

    public function down(): void {
        Schema::table('cobrancas', function (Blueprint $table) {
            if (Schema::hasColumn('cobrancas', 'net_amount')) {
                $table->dropColumn('net_amount');
            }
            if (Schema::hasColumn('cobrancas', 'fee_amount')) {
                $table->dropColumn('fee_amount');
            }
        });
    }
};
