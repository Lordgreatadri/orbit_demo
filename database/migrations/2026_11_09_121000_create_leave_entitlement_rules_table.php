<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('leave_entitlement_rules')) {
            return;
        }

        Schema::create('leave_entitlement_rules', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('leave_policy_id')->constrained('leave_policies')->cascadeOnDelete();
            $table->unsignedSmallInteger('priority')->default(100);
            $table->foreignId('staff_category_id')->nullable()->constrained('staff_categories')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('grade', 40)->nullable();
            $table->string('employment_type', 40)->nullable();
            $table->unsignedSmallInteger('min_service_months')->nullable();
            $table->unsignedSmallInteger('max_service_months')->nullable();
            $table->integer('entitled_days');
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->timestamps();

            $table->index(['leave_policy_id', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_entitlement_rules');
    }
};
