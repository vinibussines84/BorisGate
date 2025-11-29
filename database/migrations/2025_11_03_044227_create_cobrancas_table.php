<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cobrancas', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->decimal('amount', 12, 2);

            // pending | paid | failed | canceled | refunded (ajuste conforme seu fluxo)
            $table->string('status', 20)->default('pending')->index();

            // para idempotência: você pode usar o seu próprio external_id
            $table->string('external_id', 100)->nullable()->index();

            // ID/refs do provedor
            $table->string('provider', 50)->default('gateway')->index();
            $table->string('provider_transaction_id', 191)->nullable()->index();

            // dados úteis do QR / payload
            $table->longText('qrcode')->nullable();
            $table->json('payload')->nullable();    // resposta crua/normalizada do gateway
            $table->json('payer')->nullable();      // snapshot do pagador usado

            // momentos
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('canceled_at')->nullable();

            // índices auxiliares
            $table->unique(['user_id', 'external_id']); // idempotência por usuário
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cobrancas');
    }
};
