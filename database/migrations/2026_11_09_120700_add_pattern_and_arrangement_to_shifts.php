<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('shifts', 'work_pattern_id')) {
            return;
        }

        Schema::table('shifts', function (Blueprint $table): void {
            $table->foreignId('work_pattern_id')->nullable()->after('location_id')->constrained('work_patterns')->nullOnDelete();
            $table->foreignId('work_arrangement_id')->nullable()->after('work_pattern_id')->constrained('work_arrangements')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('shifts', 'work_pattern_id')) {
            return;
        }

        Schema::table('shifts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('work_pattern_id');
            $table->dropConstrainedForeignId('work_arrangement_id');
        });
    }
};
