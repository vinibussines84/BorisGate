<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('provider_in_ref')
                ->nullable()
                ->comment('Pode ser providers.code ou providers.provider_in');
            $table->string('provider_out_ref')
                ->nullable()
                ->comment('Pode ser providers.code ou providers.provider_out');

            $table->index('provider_in_ref');
            $table->index('provider_out_ref');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['provider_in_ref']);
            $table->dropIndex(['provider_out_ref']);
            $table->dropColumn(['provider_in_ref', 'provider_out_ref']);
        });
    }
};
