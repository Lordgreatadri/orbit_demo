<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional organisation wording. Channel `all` replaces the default subject
 * and body. The server fills placeholders; the stored text is not executed.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('notification_templates')) {
            return;
        }

        Schema::create('notification_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('event', 64);
            $table->string('channel', 16)->default('all');
            $table->string('subject', 140);
            $table->string('body', 1000);
            $table->timestamps();

            $table->unique(['organization_id', 'event', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_templates');
    }
};
