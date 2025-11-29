<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
  public function up(): void
{
    Schema::table('users', function (Blueprint $table) {
        // CASH IN
        $table->boolean('tax_in_enabled')->default(false);
        $table->enum('tax_in_mode', ['fixo', 'percentual'])->default('percentual');
        $table->decimal('tax_in_fixed', 10, 2)->nullable();
        $table->decimal('tax_in_percent', 5, 2)->nullable();

        // CASH OUT
        $table->boolean('tax_out_enabled')->default(false);
        $table->enum('tax_out_mode', ['fixo', 'percentual'])->default('percentual');
        $table->decimal('tax_out_fixed', 10, 2)->nullable();
        $table->decimal('tax_out_percent', 5, 2)->nullable();
    });
}

public function down(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->dropColumn([
            'tax_in_enabled','tax_in_mode','tax_in_fixed','tax_in_percent',
            'tax_out_enabled','tax_out_mode','tax_out_fixed','tax_out_percent',
        ]);
    });
}

};
