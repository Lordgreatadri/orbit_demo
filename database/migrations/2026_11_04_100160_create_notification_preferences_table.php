<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('event', 64);
            $table->boolean('mail')->default(true);
            $table->boolean('sms')->default(false);
            $table->boolean('database')->default(true);
            $table->boolean('push')->default(false);
            $table->timestamps();

            $table->unique(['organization_id', 'event']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};
