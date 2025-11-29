<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('transactions', 'idempotency_key')) {
                $table->uuid('idempotency_key')->nullable()->index();
            }
            $table->unique(['user_id','idempotency_key'],'uniq_user_idempotency');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropUnique('uniq_user_idempotency');
        });
    }
};
