<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('provider_id')->nullable()->constrained('providers')->nullOnDelete();
            $table->string('provider_in')->nullable()->default('#01GET');
            $table->string('provider_out')->nullable()->default('#02-get');
            $table->json('provider_credentials')->nullable(); // tokens/chaves do user p/ esse provider
        });
    }

    public function down(): void {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('provider_id');
            $table->dropColumn(['provider_in','provider_out','provider_credentials']);
        });
    }
};
