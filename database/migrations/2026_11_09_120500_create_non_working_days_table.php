<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('non_working_days')) {
            return;
        }

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
    }

    public function down(): void
    {
        Schema::dropIfExists('non_working_days');
    }
};
