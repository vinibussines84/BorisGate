<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ⚠️ SQLite exige recriar a tabela para remover foreign keys
        if (DB::getDriverName() === 'sqlite') {
            $this->rebuildTableWithoutWebhookId();
            return;
        }

        Schema::table('webhook_logs', function (Blueprint $table) {
            if (Schema::hasColumn('webhook_logs', 'webhook_id')) {
                $table->dropForeign(['webhook_id']);
                $table->dropColumn('webhook_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('webhook_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('webhook_logs', 'webhook_id')) {
                $table->unsignedBigInteger('webhook_id')->nullable()->after('id');
            }
        });
    }

    /**
     * Recria a tabela (modo compatível com SQLite)
     */
    private function rebuildTableWithoutWebhookId(): void
    {
        Schema::create('webhook_logs_tmp', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('type', 10)->nullable();
            $table->string('url')->nullable();
            $table->json('payload')->nullable();
            $table->string('status', 20)->default('pending');
            $table->integer('response_code')->nullable();
            $table->longText('response_body')->nullable();
            $table->timestamps();
        });

        // Copia os dados antigos (sem webhook_id)
        DB::statement('INSERT INTO webhook_logs_tmp (id, user_id, type, url, payload, status, response_code, response_body, created_at, updated_at)
                       SELECT id, user_id, type, url, payload, status, response_code, response_body, created_at, updated_at
                       FROM webhook_logs');

        // Remove tabela antiga e renomeia a nova
        Schema::drop('webhook_logs');
        Schema::rename('webhook_logs_tmp', 'webhook_logs');
    }
};
