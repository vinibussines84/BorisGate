<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Não recria se já existir
        if (Schema::hasTable('transactions')) {
            return;
        }

        Schema::create('transactions', function (Blueprint $table) {
            $table->id();

            // usuário (FK opcional; se users existe, o Laravel cria FK automaticamente)
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // valores
            $table->decimal('amount', 14, 2);
            $table->decimal('fee', 14, 2)->default(0);

            // coluna gerada (virtual é suficiente; não tente atualizar via Eloquent)
            $table->decimal('net_amount', 14, 2)->virtualAs('`amount` - `fee`');

            // natureza e status
            $table->enum('direction', ['in', 'out'])->comment('in=entrada, out=saída');
            $table->enum('status', [
                'pending','processing','paid','failed','refunded','canceled','chargeback',
            ])->default('pending');

            // metadados
            $table->string('currency', 3)->default('BRL');
            $table->string('method')->nullable()->comment('pix, boleto, card, transfer, etc.');

            // provedor + refs externas
            $table->string('provider')->index();
            $table->string('provider_transaction_id')->nullable();
            $table->string('external_reference')->nullable()->comment('sua referência externa / order id');

            // payload e descrição
            $table->json('provider_payload')->nullable();
            $table->string('description', 255)->nullable();

            // datas ciclo de vida
            $table->timestamp('authorized_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamp('canceled_at')->nullable();

            // segurança/auditoria
            $table->string('idempotency_key')->nullable()->unique();
            $table->ipAddress('ip')->nullable();
            $table->string('user_agent')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // índices úteis
            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index(['direction', 'created_at']);
            $table->unique(['provider', 'provider_transaction_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
