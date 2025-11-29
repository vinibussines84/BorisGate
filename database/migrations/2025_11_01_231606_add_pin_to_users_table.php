<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Armazena o PIN criptografado (Hash::make)
            // "after('password')" é opcional; em SQLite será ignorado.
            if (!Schema::hasColumn('users', 'pin')) {
                $table->string('pin')->nullable()->after('password');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'pin')) {
                $table->dropColumn('pin');
            }
        });
    }
};
