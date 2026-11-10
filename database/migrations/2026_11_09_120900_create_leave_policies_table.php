<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('leave_policies')) {
            return;
        }

        Schema::create('leave_policies', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained('leave_types')->cascadeOnDelete();
            $table->string('name', 120);
            $table->unsignedTinyInteger('year_start_month')->default(1);
            $table->unsignedTinyInteger('year_start_day')->default(1);
            $table->string('count_mode', 16)->default('calendar');
            $table->json('weekend_days');
            $table->boolean('exclude_non_working')->default(true);
            $table->string('approval_mode', 24)->default('any_approver');
            $table->boolean('allow_self_approval')->default(false);
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['organization_id', 'leave_type_id', 'effective_from'], 'leave_policies_type_effective');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_policies');
    }
};
