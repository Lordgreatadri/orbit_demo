<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One workforce notice claim per recipient, key, and channel.
 *
 * Historical filename: 2026_11_10_090000_schedule_leave_legacy_backfill.
 * That name was applied on the local development database only. It is not a
 * production release. This file creates the table and does not backfill rows.
 * Repair of leave accounts and attendance links is
 * `php artisan orbit:data:backfill-workforce-v1`.
 * Rolling back drops the notice table only. Do not roll this file back on a
 * database that created the table under the historical filename.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('workforce_notice_deliveries')) {
            return;
        }

        Schema::create('workforce_notice_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('dedupe_key', 80);
            $table->string('channel', 16);
            $table->timestamps();

            $table->unique(['user_id', 'dedupe_key', 'channel'], 'workforce_notice_delivery_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workforce_notice_deliveries');
    }
};
