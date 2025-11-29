<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('kyc_reports', function (Blueprint $t) {
      $t->id();
      $t->foreignId('user_id')->constrained()->cascadeOnDelete();
      $t->string('status')->default('pending'); // pending|approved|rejected
      $t->json('metrics')->nullable();
      $t->json('reasons')->nullable();
      $t->timestamps();
    });

    Schema::table('users', function (Blueprint $t) {
      if (!Schema::hasColumn('users','cpf')) $t->string('cpf')->nullable()->unique();
      if (!Schema::hasColumn('users','selfie_path')) $t->string('selfie_path')->nullable();
      if (!Schema::hasColumn('users','doc_front_path')) $t->string('doc_front_path')->nullable();
      if (!Schema::hasColumn('users','doc_back_path')) $t->string('doc_back_path')->nullable();
      if (!Schema::hasColumn('users','kyc_status')) $t->string('kyc_status')->default('pending');
    });
  }

  public function down(): void {
    Schema::dropIfExists('kyc_reports');
    Schema::table('users', function (Blueprint $t) {
      if (Schema::hasColumn('users','kyc_status')) $t->dropColumn('kyc_status');
      if (Schema::hasColumn('users','doc_back_path')) $t->dropColumn('doc_back_path');
      if (Schema::hasColumn('users','doc_front_path')) $t->dropColumn('doc_front_path');
      if (Schema::hasColumn('users','selfie_path')) $t->dropColumn('selfie_path');
      if (Schema::hasColumn('users','cpf')) $t->dropColumn('cpf');
    });
  }
};