<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A calendar day can hold more than one attendance record when the records
 * belong to different sessions or work periods. A rotating QR code stays valid
 * for every eligible person until it expires; it is not consumed by the first scan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_records', function (Blueprint $table): void {
            $table->foreignId('attendance_session_id')->nullable()->after('location_id')->constrained('attendance_sessions');
            $table->string('period_key', 64)->nullable()->after('work_date');
        });

        DB::table('attendance_records')->orderBy('id')->chunkById(200, function ($rows): void {
            foreach ($rows as $row) {
                DB::table('attendance_records')->where('id', $row->id)->update([
                    'period_key' => 'day:'.substr((string) $row->work_date, 0, 10),
                ]);
            }
        });

        Schema::table('attendance_records', function (Blueprint $table): void {
            $table->dropUnique(['organization_id', 'staff_member_id', 'work_date']);
        });

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE attendance_records ALTER COLUMN period_key SET NOT NULL');
        }

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE attendance_records MODIFY period_key VARCHAR(64) NOT NULL');
        }

        Schema::table('attendance_records', function (Blueprint $table): void {
            $table->unique(['organization_id', 'staff_member_id', 'period_key'], 'attendance_records_period_unique');
        });

        Schema::table('attendance_tokens', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('consumed_by_staff_member_id');
            $table->dropColumn('consumed_at');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_tokens', function (Blueprint $table): void {
            $table->timestamp('consumed_at')->nullable();
            $table->foreignId('consumed_by_staff_member_id')->nullable()->constrained('staff_members')->nullOnDelete();
        });

        Schema::table('attendance_records', function (Blueprint $table): void {
            $table->dropUnique('attendance_records_period_unique');
            $table->dropConstrainedForeignId('attendance_session_id');
            $table->dropColumn('period_key');
            $table->unique(['organization_id', 'staff_member_id', 'work_date']);
        });
    }
};
