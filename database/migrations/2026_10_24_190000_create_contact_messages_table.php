<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Public contact form submissions (platform-level, not tenant-owned).
     * Keep retention policy in mind: purge handled rows on a schedule later.
     */
    public function up(): void
    {
        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name', 120);
            $table->string('email');
            $table->string('organization_name', 160)->nullable();
            $table->string('subject', 160);
            $table->text('message');
            $table->string('ip_address', 45)->nullable();
            $table->string('status', 32)->default('new');
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
    }
};
