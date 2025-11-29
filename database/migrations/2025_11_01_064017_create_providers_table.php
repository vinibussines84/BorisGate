<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('providers', function (Blueprint $table) {
            $table->id();
            $table->string('name');                 // Nome legível
            $table->string('code')->unique();       // ex: getpay
            $table->string('service_class');        // ex: App\Services\GetpayService
            $table->string('provider_in')->nullable()->default('#01GET');  // código/alias para IN
            $table->string('provider_out')->nullable()->default('#02-get'); // código/alias para OUT
            $table->json('config')->nullable();     // configs gerais do provider (opcional)
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void {
        Schema::dropIfExists('providers');
    }
};
