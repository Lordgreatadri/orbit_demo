<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Work periods can repeat on one date. Leave entitlement is a tenant policy
 * and a set of rules, not a single number on the leave type.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_arrangements', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('code', 32);
            $table->string('kind', 16);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'code']);
        });

        Schema::create('staff_categories', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('code', 32);
            $table->timestamps();

            $table->unique(['organization_id', 'code']);
        });

        Schema::table('staff_members', function (Blueprint $table): void {
            $table->foreignId('staff_category_id')->nullable()->after('department_id')->constrained('staff_categories')->nullOnDelete();
            $table->foreignId('reports_to_id')->nullable()->after('staff_category_id')->constrained('staff_members')->nullOnDelete();
            $table->string('grade', 40)->nullable()->after('job_title');
            $table->string('employment_type', 40)->nullable()->after('grade');
        });

        Schema::table('departments', function (Blueprint $table): void {
            $table->foreignId('head_staff_member_id')->nullable()->after('description')->constrained('staff_members')->nullOnDelete();
        });

        Schema::create('non_working_days', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->date('observed_on');
            $table->string('name', 120);
            $table->string('kind', 16)->default('holiday');
            $table->timestamps();

            $table->unique(['organization_id', 'observed_on']);
        });

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

        Schema::table('shifts', function (Blueprint $table): void {
            $table->foreignId('work_pattern_id')->nullable()->after('location_id')->constrained('work_patterns')->nullOnDelete();
            $table->foreignId('work_arrangement_id')->nullable()->after('work_pattern_id')->constrained('work_arrangements')->nullOnDelete();
        });

        Schema::table('shift_assignments', function (Blueprint $table): void {
            $table->dropUnique(['organization_id', 'staff_member_id', 'work_date']);
            $table->foreignId('work_arrangement_id')->nullable()->after('staff_member_id')->constrained('work_arrangements')->nullOnDelete();
            $table->foreignId('location_id')->nullable()->after('work_arrangement_id')->constrained('locations')->nullOnDelete();
            $table->foreignId('work_pattern_id')->nullable()->after('location_id')->constrained('work_patterns')->nullOnDelete();
            $table->timestamp('reminded_at')->nullable()->after('work_date');
            $table->unique(['organization_id', 'staff_member_id', 'work_date', 'shift_id'], 'shift_assignments_period_unique');
        });

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

        Schema::table('leave_balances', function (Blueprint $table): void {
            $table->date('period_start')->default('2000-01-01')->after('year');
            $table->date('period_end')->default('2000-12-31')->after('period_start');
            $table->integer('pending_days')->default(0)->after('used_days');
            $table->integer('adjusted_days')->default(0)->after('pending_days');
        });

        DB::table('leave_balances')->orderBy('id')->chunkById(200, function ($rows): void {
            foreach ($rows as $row) {
                $start = sprintf('%04d-01-01', (int) $row->year);
                DB::table('leave_balances')->where('id', $row->id)->update([
                    'period_start' => $start,
                    'period_end' => sprintf('%04d-12-31', (int) $row->year),
                ]);
            }
        });

        Schema::table('leave_balances', function (Blueprint $table): void {
            $table->dropUnique(['staff_member_id', 'leave_type_id', 'year']);
            $table->unique(['staff_member_id', 'leave_type_id', 'period_start'], 'leave_balances_period_unique');
        });

        Schema::create('leave_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('leave_balance_id')->constrained('leave_balances')->cascadeOnDelete();
            $table->foreignId('staff_member_id')->constrained('staff_members')->cascadeOnDelete();
            $table->integer('days');
            $table->string('reason', 500);
            $table->foreignId('adjusted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'staff_member_id', 'created_at'], 'leave_adjustments_staff_created');
        });

        Schema::table('leave_requests', function (Blueprint $table): void {
            $table->foreignId('leave_policy_id')->nullable()->after('leave_type_id')->constrained('leave_policies')->nullOnDelete();
            $table->unsignedSmallInteger('day_count')->default(0)->after('ends_on');
            $table->json('covered_dates')->nullable()->after('day_count');
        });

        DB::table('leave_requests')->orderBy('id')->chunkById(200, function ($rows): void {
            foreach ($rows as $row) {
                $start = Carbon::parse($row->starts_on);
                $end = Carbon::parse($row->ends_on);
                $dates = [];
                $cursor = $start->copy();
                while ($cursor->lte($end) && count($dates) < 366) {
                    $dates[] = $cursor->toDateString();
                    $cursor->addDay();
                }
                DB::table('leave_requests')->where('id', $row->id)->update([
                    'day_count' => count($dates),
                    'covered_dates' => json_encode($dates),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('leave_policy_id');
            $table->dropColumn(['day_count', 'covered_dates']);
        });

        Schema::dropIfExists('leave_adjustments');

        Schema::table('leave_balances', function (Blueprint $table): void {
            $table->dropUnique('leave_balances_period_unique');
            $table->unique(['staff_member_id', 'leave_type_id', 'year']);
            $table->dropColumn(['period_start', 'period_end', 'pending_days', 'adjusted_days']);
        });

        Schema::dropIfExists('leave_entitlement_rules');
        Schema::dropIfExists('leave_policies');

        Schema::table('shift_assignments', function (Blueprint $table): void {
            $table->dropUnique('shift_assignments_period_unique');
            $table->dropConstrainedForeignId('work_arrangement_id');
            $table->dropConstrainedForeignId('location_id');
            $table->dropConstrainedForeignId('work_pattern_id');
            $table->dropColumn('reminded_at');
            $table->unique(['organization_id', 'staff_member_id', 'work_date']);
        });

        Schema::table('shifts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('work_pattern_id');
            $table->dropConstrainedForeignId('work_arrangement_id');
        });

        Schema::dropIfExists('work_patterns');
        Schema::dropIfExists('non_working_days');

        Schema::table('departments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('head_staff_member_id');
        });

        Schema::table('staff_members', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('reports_to_id');
            $table->dropConstrainedForeignId('staff_category_id');
            $table->dropColumn(['grade', 'employment_type']);
        });

        Schema::dropIfExists('staff_categories');
        Schema::dropIfExists('work_arrangements');
    }
};
