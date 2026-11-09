<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workforce_policies', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->unique()->constrained('organizations')->cascadeOnDelete();
            $table->unsignedSmallInteger('grace_minutes')->default(10);
            $table->time('work_start')->default('08:00:00');
            $table->time('work_end')->default('17:00:00');
            $table->unsignedSmallInteger('qr_ttl_seconds')->default(30);
            $table->unsignedSmallInteger('max_gps_accuracy_m')->default(100);
            $table->unsignedSmallInteger('location_max_age_seconds')->default(120);
            $table->boolean('remote_without_geofence')->default(true);
            $table->boolean('require_device_binding')->default(false);
            $table->boolean('otp_enabled')->default(false);
            $table->unsignedSmallInteger('field_delay_minutes')->default(60);
            $table->json('enabled_methods');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workforce_policies');
    }
};
