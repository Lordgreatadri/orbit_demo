<?php

use App\Workforce\LegacyWorkforceBackfill;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Repair rows that already existed when schedule and leave policy shipped:
 * pending reservations, calendar-year policy links, and unambiguous
 * attendance-to-assignment links. Also records a workforce notice once per
 * recipient, key, and channel. Rolling back drops the notice table only.
 * The historical links stay, because unlinking them would be a guess.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workforce_notice_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('dedupe_key', 80);
            $table->string('channel', 16);
            $table->timestamps();

            $table->unique(['user_id', 'dedupe_key', 'channel'], 'workforce_notice_delivery_unique');
        });

        LegacyWorkforceBackfill::leaveAccounts();
        LegacyWorkforceBackfill::attendanceLinks();
    }

    public function down(): void
    {
        Schema::dropIfExists('workforce_notice_deliveries');
    }
};
