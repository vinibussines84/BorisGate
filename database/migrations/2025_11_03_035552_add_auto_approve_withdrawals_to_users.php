<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'auto_approve_withdrawals')) {
                $table->boolean('auto_approve_withdrawals')->default(false)->after('email');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'auto_approve_withdrawals')) {
                $table->dropColumn('auto_approve_withdrawals');
            }
        });
    }
};
