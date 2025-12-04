<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("
            ALTER TABLE transactions 
            MODIFY COLUMN status ENUM('PENDING','PROCESSING','PAID','FAILED','ERROR') 
            NOT NULL
        ");
    }

    public function down(): void
    {
        // nada a desfazer
    }
};
