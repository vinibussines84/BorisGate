<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('withdraws', function (Blueprint $table) {

            // Referência devolvida pela Pluggou no webhook
            if (!Schema::hasColumn('withdraws', 'provider_reference')) {
                $table->string('provider_reference', 120)
                    ->nullable()
                    ->after('idempotency_key');
            }

            // Mensagem devolvida pela Pluggou (opcional)
            if (!Schema::hasColumn('withdraws', 'provider_message')) {
                $table->string('provider_message', 255)
                    ->nullable()
                    ->after('provider_reference');
            }

            // Coluna que salva JSON de retorno
            if (!Schema::hasColumn('withdraws', 'meta')) {
                $table->json('meta')
                    ->nullable()
                    ->after('provider_message');
            }

            // Quando a transação realmente foi concluída
            if (!Schema::hasColumn('withdraws', 'completed_at')) {
                $table->timestamp('completed_at')
                    ->nullable()
                    ->after('updated_at');
            }
        });
    }

    public function down()
    {
        Schema::table('withdraws', function (Blueprint $table) {
            $table->dropColumn([
                'provider_reference',
                'provider_message',
                'meta',
                'completed_at',
            ]);
        });
    }
};




