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
        Schema::table('pix_transactions', function (Blueprint $table) {
            // Adiciona external_id
            $table->string('external_id')->nullable()->index()->after('id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pix_transactions', function (Blueprint $table) {
            // Remove external_id
            $table->dropColumn('external_id');
        });
    }
};
