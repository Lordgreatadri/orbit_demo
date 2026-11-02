<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_providers', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('key', 40)->unique();
            $table->string('name');
            $table->boolean('enabled')->default(false);
            $table->boolean('is_active')->default(false);
            $table->boolean('is_fallback')->default(false);
            $table->string('sender_id', 34)->nullable();
            $table->text('credentials')->nullable();
            $table->unsignedSmallInteger('timeout_seconds')->default(10);
            $table->unsignedTinyInteger('max_attempts')->default(3);
            $table->json('backoff_seconds');
            $table->unsignedSmallInteger('requests_per_minute')->default(30);
            $table->decimal('low_balance_threshold', 14, 2)->nullable();
            $table->integer('low_bundle_threshold')->nullable();
            $table->decimal('balance', 14, 2)->nullable();
            $table->string('balance_unit', 16)->nullable();
            $table->decimal('cash_balance', 14, 2)->nullable();
            $table->integer('sms_bundle_balance')->nullable();
            $table->string('cash_balance_unit', 16)->nullable();
            $table->timestamp('balance_checked_at')->nullable();
            $table->timestamp('low_balance_notified_at')->nullable();
            $table->string('health_status', 20)->default('unknown');
            $table->timestamp('health_checked_at')->nullable();
            $table->string('health_message', 300)->nullable();
            $table->boolean('sandbox')->default(false);
            $table->timestamps();
        });

        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['pgsql', 'sqlite'], true)) {
            $active = $driver === 'pgsql' ? 'true' : '1';
            DB::statement("CREATE UNIQUE INDEX sms_providers_one_active ON sms_providers (is_active) WHERE is_active = {$active}");
            DB::statement("CREATE UNIQUE INDEX sms_providers_one_fallback ON sms_providers (is_fallback) WHERE is_fallback = {$active}");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_providers');
    }
};
