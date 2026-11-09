<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('field_activities', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('field_assignment_id')->constrained('field_assignments')->cascadeOnDelete();
            $table->foreignId('staff_member_id')->constrained('staff_members')->cascadeOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->string('status', 16)->default('active');
            $table->decimal('last_latitude', 10, 7)->nullable();
            $table->decimal('last_longitude', 10, 7)->nullable();
            $table->unsignedInteger('last_accuracy_m')->nullable();
            $table->timestamp('last_reported_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status', 'last_reported_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('field_activities');
    }
};
