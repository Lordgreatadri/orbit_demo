<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One communication policy per organisation. Reading the policy does not
 * insert a row; this table appears when an administrator saves it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('communication_policies')) {
            return;
        }

        Schema::create('communication_policies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->unique()->constrained('organizations')->cascadeOnDelete();
            $table->boolean('email_enabled')->default(true);
            $table->boolean('sms_enabled')->default(true);
            $table->boolean('in_app_enabled')->default(true);
            $table->boolean('leave_notifications_enabled')->default(true);
            $table->boolean('schedule_notifications_enabled')->default(true);
            $table->boolean('attendance_reminders_enabled')->default(true);
            $table->boolean('announcements_enabled')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_policies');
    }
};
