<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('withdraws', function (Blueprint $table) {
            $table->string('external_id')
                ->nullable()
                ->index()
                ->after('idempotency_key');
        });
    }

    public function down(): void
    {
        Schema::table('withdraws', function (Blueprint $table) {
            $table->dropColumn('external_id');
        });
    }
};
