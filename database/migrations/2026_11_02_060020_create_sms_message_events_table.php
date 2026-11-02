<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_message_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sms_message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sms_provider_id')->nullable()->constrained('sms_providers')->nullOnDelete();
            $table->string('provider_key', 40);
            $table->string('type', 30);
            $table->string('status', 20)->nullable();
            $table->string('provider_status', 40)->nullable();
            $table->string('event_key', 160)->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['provider_key', 'event_key']);
            $table->index(['sms_message_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_message_events');
    }
};
