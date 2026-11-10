<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('leave_adjustments')) {
            return;
        }

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
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_adjustments');
    }
};
