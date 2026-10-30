<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Staff records are the organisation's people register (employee/student ID,
     * department, work type, primary location). They are distinct from `users`,
     * which are login identities with roles. A staff record may optionally be
     * linked to exactly one user account, and vice versa.
     */
    public function up(): void
    {
        Schema::create('staff_members', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('primary_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->string('staff_number', 64);
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('email')->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('job_title', 120)->nullable();
            $table->string('work_type', 16)->default('office'); // office | field | remote | hybrid
            $table->string('status', 16)->default('active'); // active | on_leave | inactive | exited
            $table->date('started_on')->nullable();
            $table->date('ended_on')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'staff_number']);
            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'department_id']);
            $table->index(['organization_id', 'work_type']);
            $table->index(['organization_id', 'last_name', 'first_name']);
            $table->index(['organization_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_members');
    }
};
