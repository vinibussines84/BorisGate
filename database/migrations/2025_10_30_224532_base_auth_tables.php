<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // USERS
        Schema::create('users', function (Blueprint $table) {
            $table->id();

            // Perfil
            $table->string('nome_completo');                 // ex.: "Ciro Oliveira"
            $table->date('data_nascimento')->nullable();     // ex.: 1990-01-15

            // Identificação (opcionais)
            $table->string('cpf_cnpj')->nullable()->index(); // pode ser único depois que tiver dados confiáveis
            $table->string('tenant_key')->nullable()->index();
            $table->string('auth_key')->nullable()->index();

            // Financeiro
            $table->decimal('amount_available', 15, 2)->default(0);
            $table->decimal('amount_retained', 15, 2)->default(0);
            $table->decimal('blocked_amount', 15, 2)->default(0);

            // Status (string por compatibilidade com SQLite)
            // valores esperados: 'ativo' | 'bloqueado'
            $table->string('user_status', 20)->default('ativo')->index();

            // Padrão Laravel
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken(); // varchar(100) nullable
            $table->timestamps();
        });

        // PASSWORD RESET TOKENS
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        // SESSIONS
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
