<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stores the policy and the days a request covered when it was submitted.
 * Existing rows are filled by orbit:data:backfill-leave-requests and
 * orbit:data:backfill-workforce-v1.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('leave_requests', 'day_count')) {
            return;
        }

        Schema::table('leave_requests', function (Blueprint $table): void {
            $table->foreignId('leave_policy_id')->nullable()->after('leave_type_id')->constrained('leave_policies')->nullOnDelete();
            $table->unsignedSmallInteger('day_count')->default(0)->after('ends_on');
            $table->json('covered_dates')->nullable()->after('day_count');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('leave_requests', 'day_count')) {
            return;
        }

        Schema::table('leave_requests', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('leave_policy_id');
            $table->dropColumn(['day_count', 'covered_dates']);
        });
    }
};
