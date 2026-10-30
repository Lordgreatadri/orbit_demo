<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Approved places where attendance may be recorded (requirements 04).
     * Coordinates and radius are optional until the attendance module needs them;
     * when present they are validated server-side (lat -90..90, lng -180..180,
     * radius 10..5000 m).
     */
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('type', 24)->default('office'); // office | field_area | remote
            $table->string('address_line')->nullable();
            $table->string('city', 120)->nullable();
            $table->string('district', 255)->nullable();
            $table->string('region', 120)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedSmallInteger('geofence_radius_m')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'name']);
            $table->index(['organization_id', 'type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
