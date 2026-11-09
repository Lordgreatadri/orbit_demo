<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('field_activities', function (Blueprint $table): void {
            $table->foreignId('attendance_record_id')
                ->nullable()
                ->after('staff_member_id')
                ->constrained('attendance_records')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('field_activities', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('attendance_record_id');
        });
    }
};
