<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('shift_assignments', 'work_arrangement_id')) {
            return;
        }

        Schema::table('shift_assignments', function (Blueprint $table): void {
            $table->dropUnique(['organization_id', 'staff_member_id', 'work_date']);
            $table->foreignId('work_arrangement_id')->nullable()->after('staff_member_id')->constrained('work_arrangements')->nullOnDelete();
            $table->foreignId('location_id')->nullable()->after('work_arrangement_id')->constrained('locations')->nullOnDelete();
            $table->foreignId('work_pattern_id')->nullable()->after('location_id')->constrained('work_patterns')->nullOnDelete();
            $table->timestamp('reminded_at')->nullable()->after('work_date');
            $table->unique(['organization_id', 'staff_member_id', 'work_date', 'shift_id'], 'shift_assignments_period_unique');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('shift_assignments', 'work_arrangement_id')) {
            return;
        }

        Schema::table('shift_assignments', function (Blueprint $table): void {
            $table->dropUnique('shift_assignments_period_unique');
            $table->dropConstrainedForeignId('work_arrangement_id');
            $table->dropConstrainedForeignId('location_id');
            $table->dropConstrainedForeignId('work_pattern_id');
            $table->dropColumn('reminded_at');
            $table->unique(['organization_id', 'staff_member_id', 'work_date']);
        });
    }
};
