<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bound_devices', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->char('device_hash', 64);
            $table->string('label', 80)->nullable();
            $table->timestamp('confirmed_at');
            $table->timestamps();

            $table->unique(['organization_id', 'user_id', 'device_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bound_devices');
    }
};
