<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Assignment clocks are copied at publish time so a later template edit
 * does not rewrite the period attendance already explained.
 * A check-in during a published period is stored against that assignment.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shift_assignments', function (Blueprint $table): void {
            $table->time('starts_at')->nullable()->after('work_date');
            $table->time('ends_at')->nullable()->after('starts_at');
            $table->boolean('crosses_midnight')->default(false)->after('ends_at');
        });

        DB::table('shift_assignments')->orderBy('id')->chunkById(200, function ($rows): void {
            foreach ($rows as $row) {
                $shift = DB::table('shifts')->where('id', $row->shift_id)->first();

                if ($shift === null) {
                    continue;
                }

                DB::table('shift_assignments')->where('id', $row->id)->update([
                    'starts_at' => $shift->starts_at,
                    'ends_at' => $shift->ends_at,
                    'crosses_midnight' => $shift->crosses_midnight,
                ]);
            }
        });

        Schema::table('attendance_records', function (Blueprint $table): void {
            $table->foreignId('shift_assignment_id')->nullable()->after('attendance_session_id')->constrained('shift_assignments')->nullOnDelete();
        });

        Schema::table('leave_requests', function (Blueprint $table): void {
            $table->index(['organization_id', 'staff_member_id', 'status', 'starts_on'], 'leave_requests_staff_status_start');
        });

        $this->balanceCheck();
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('alter table leave_balances drop constraint if exists leave_balances_within_entitlement');
        }

        if ($driver === 'mysql') {
            DB::statement('alter table leave_balances drop check leave_balances_within_entitlement');
        }

        Schema::table('leave_requests', function (Blueprint $table): void {
            $table->dropIndex('leave_requests_staff_status_start');
        });

        Schema::table('attendance_records', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('shift_assignment_id');
        });

        Schema::table('shift_assignments', function (Blueprint $table): void {
            $table->dropColumn(['starts_at', 'ends_at', 'crosses_midnight']);
        });
    }

    /**
     * SQLite cannot add a CHECK constraint with ALTER TABLE. Tests therefore
     * prove the invariant in the service. PostgreSQL and MySQL enforce it.
     */
    private function balanceCheck(): void
    {
        if (! in_array(Schema::getConnection()->getDriverName(), ['pgsql', 'mysql'], true)) {
            return;
        }

        DB::statement('alter table leave_balances add constraint leave_balances_within_entitlement check ((pending_days + used_days) <= (entitled_days + adjusted_days))');
    }
};
