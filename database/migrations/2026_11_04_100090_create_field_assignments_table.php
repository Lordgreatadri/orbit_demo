<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('field_assignments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('staff_member_id')->constrained('staff_members')->cascadeOnDelete();
            $table->foreignId('location_id')->constrained('locations')->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 160);
            $table->text('instructions')->nullable();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('status', 16)->default('assigned');
            $table->timestamps();

            $table->index(['organization_id', 'status', 'starts_on']);
            $table->index(['organization_id', 'staff_member_id', 'starts_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('field_assignments');
    }
};
