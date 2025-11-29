<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Em SQLite o JSON vira TEXT; o cast do Eloquent resolve
            if (!Schema::hasColumn('users', 'is_admin')) {
                $table->boolean('is_admin')->default(false)->after('user_status');
            }
            if (!Schema::hasColumn('users', 'role')) {
                $table->string('role', 32)->nullable()->after('is_admin');
            }
            if (!Schema::hasColumn('users', 'permissions')) {
                // use json() em bancos que suportam; em SQLite vira TEXT
                $table->json('permissions')->nullable()->after('role');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'permissions')) $table->dropColumn('permissions');
            if (Schema::hasColumn('users', 'role'))        $table->dropColumn('role');
            if (Schema::hasColumn('users', 'is_admin'))    $table->dropColumn('is_admin');
        });
    }
};
