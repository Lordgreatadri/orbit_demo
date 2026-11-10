<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who read which in-app notice, and when. `notifications.read_at` remains the
 * operational flag. This table is the audit, unique per organisation, notice,
 * and user, so a second click does not add a row. Notices already read before
 * this table existed are copied by `php artisan orbit:data:backfill-notification-reads`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notification_read_audits')) {
            Schema::create('notification_read_audits', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
                $table->foreignId('notification_id')->constrained('notifications')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->timestamp('read_at');
                $table->timestamps();

                $table->unique(['organization_id', 'notification_id', 'user_id'], 'notification_read_once');
                $table->index(['organization_id', 'user_id', 'read_at'], 'notification_reads_by_user');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_read_audits');
    }
};
