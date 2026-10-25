<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Central (platform) table: one row per tenant organisation.
     * Sorted ahead of the users table so the users FK can reference it.
     */
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name', 160);
            $table->string('slug', 180)->unique();
            $table->string('type', 64);
            $table->string('status', 32)->default('pending_verification');
            $table->string('timezone', 64)->default('Africa/Accra');
            $table->char('country_code', 2)->default('GH');
            $table->string('contact_email');
            $table->string('contact_phone', 32)->nullable();
            $table->string('address_line')->nullable();
            $table->string('city', 120)->nullable();
            $table->string('logo_path')->nullable();
            $table->json('settings')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
