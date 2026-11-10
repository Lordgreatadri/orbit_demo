<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('staff_members', 'staff_category_id')) {
            return;
        }

        Schema::table('staff_members', function (Blueprint $table): void {
            $table->foreignId('staff_category_id')->nullable()->after('department_id')->constrained('staff_categories')->nullOnDelete();
            $table->foreignId('reports_to_id')->nullable()->after('staff_category_id')->constrained('staff_members')->nullOnDelete();
            $table->string('grade', 40)->nullable()->after('job_title');
            $table->string('employment_type', 40)->nullable()->after('grade');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('staff_members', 'staff_category_id')) {
            return;
        }

        Schema::table('staff_members', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('reports_to_id');
            $table->dropConstrainedForeignId('staff_category_id');
            $table->dropColumn(['grade', 'employment_type']);
        });
    }
};
