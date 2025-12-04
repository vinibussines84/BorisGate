<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Se estiver usando SQLite, IGNORA.
        if (DB::getDriverName() === 'sqlite') {
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
        // Se estiver usando SQLite, IGNORA.
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("
            ALTER TABLE transactions 
            MODIFY COLUMN status ENUM('PENDING','PROCESSING','FAILED','ERROR') 
            NOT NULL
        ");
    }
};
