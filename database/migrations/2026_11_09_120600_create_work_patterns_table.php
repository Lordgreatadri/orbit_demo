<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('work_patterns')) {
            return;
        }

        Schema::create('work_patterns', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('staff_category_id')->nullable()->constrained('staff_categories')->nullOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('work_arrangement_id')->nullable()->constrained('work_arrangements')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 120);
            $table->time('starts_at');
            $table->time('ends_at');
            $table->boolean('crosses_midnight')->default(false);
            $table->json('weekdays');
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->string('status', 16)->default('draft');
            $table->timestamps();

            $table->index(['organization_id', 'status', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_patterns');
    }
};
