<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('departments', 'head_staff_member_id')) {
            return;
        }

        Schema::table('departments', function (Blueprint $table): void {
            $table->foreignId('head_staff_member_id')->nullable()->after('description')->constrained('staff_members')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('departments', 'head_staff_member_id')) {
            return;
        }

        Schema::table('departments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('head_staff_member_id');
        });
    }
};
