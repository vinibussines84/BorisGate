<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webhook_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('webhook_logs', 'user_id')) {
                $table->foreignId('user_id')->after('id')->constrained()->onDelete('cascade');
            }
            if (!Schema::hasColumn('webhook_logs', 'type')) {
                $table->string('type', 10)->after('user_id'); // in / out
            }
            if (!Schema::hasColumn('webhook_logs', 'url')) {
                $table->string('url')->after('type');
            }
            if (!Schema::hasColumn('webhook_logs', 'payload')) {
                $table->json('payload')->nullable()->after('url');
            }
            if (!Schema::hasColumn('webhook_logs', 'status')) {
                $table->string('status', 20)->default('pending')->after('payload');
            }
            if (!Schema::hasColumn('webhook_logs', 'response_code')) {
                $table->integer('response_code')->nullable()->after('status');
            }
            if (!Schema::hasColumn('webhook_logs', 'response_body')) {
                $table->longText('response_body')->nullable()->after('response_code');
            }
        });
    }

    public function down(): void
    {
        Schema::table('webhook_logs', function (Blueprint $table) {
            $table->dropColumn([
                'user_id',
                'type',
                'url',
                'payload',
                'status',
                'response_code',
                'response_body',
            ]);
        });
    }
};
