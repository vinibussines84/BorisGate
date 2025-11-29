<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('withdraws', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Requisitos do saque
            $table->decimal('amount', 12, 2);
            $table->string('description')->nullable();

            $table->string('pixkey');
            $table->enum('pixkey_type', ['phone','evp','cpf','cnpj','email']);

            $table->string('idempotency_key', 100)->unique();

            // Segurança: não armazene PIN plano
            $table->text('pin_encrypted')->nullable();

            // Ciclo de vida (útil para dashboard)
            $table->enum('status', ['pending','processing','paid','failed','canceled'])
                  ->default('pending')->index();
            $table->timestamp('processed_at')->nullable();

            $table->timestamps();

            // Índices úteis
            $table->index(['user_id', 'status']);
            $table->index('pixkey');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('withdraws');
    }
};
