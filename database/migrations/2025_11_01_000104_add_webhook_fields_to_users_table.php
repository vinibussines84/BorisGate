<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('webhook_enabled')->default(false);
            $table->string('webhook_in_url')->nullable();   // endpoint p/ eventos de entrada (cash-in)
            $table->string('webhook_out_url')->nullable();  // endpoint p/ eventos de saída (cash-out)
            $table->string('webhook_secret')->nullable();   // opcional: token/assinatura
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'webhook_enabled',
                'webhook_in_url',
                'webhook_out_url',
                'webhook_secret',
            ]);
        });
    }
};
