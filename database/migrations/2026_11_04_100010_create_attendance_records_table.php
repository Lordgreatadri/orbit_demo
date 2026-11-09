<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_records', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('staff_member_id')->constrained('staff_members')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->date('work_date');
            $table->timestamp('check_in_at')->nullable();
            $table->timestamp('check_out_at')->nullable();
            $table->string('status', 32);
            $table->string('source', 16)->default('self');
            $table->unsignedSmallInteger('late_minutes')->nullable();
            $table->unsignedSmallInteger('early_leave_minutes')->nullable();
            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'staff_member_id', 'work_date']);
            $table->index(['organization_id', 'work_date', 'status']);
            $table->index(['organization_id', 'location_id', 'work_date']);
            $table->index(['organization_id', 'department_id', 'work_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
    }
};
