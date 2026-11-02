<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_messages', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sms_provider_id')->nullable()->constrained('sms_providers')->nullOnDelete();
            $table->string('idempotency_key', 80)->unique();
            $table->string('to', 20);
            $table->text('body');
            $table->string('sender_id', 20)->nullable();
            $table->string('purpose', 40);
            $table->string('status', 20);
            $table->string('provider_message_id', 120)->nullable();
            $table->string('provider_status', 40)->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedTinyInteger('max_attempts')->default(3);
            $table->json('backoff_seconds')->nullable();
            $table->boolean('allow_fallback')->default(true);
            $table->boolean('used_fallback')->default(false);
            // Platform transmissions never count toward an organisation subscription.
            $table->boolean('counts_toward_subscription')->default(false);
            $table->string('billing_scope', 20)->default('platform');
            $table->string('last_error', 300)->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'id']);
            $table->index(['sms_provider_id', 'id']);
            $table->index('provider_message_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_messages');
    }
};
