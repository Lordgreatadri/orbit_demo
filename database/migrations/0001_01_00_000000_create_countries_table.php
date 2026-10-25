<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name', 120)->unique();
            $table->string('code', 8);
            $table->string('currency', 64)->nullable();
            $table->string('currency_symbol', 8)->nullable();
            $table->string('country_currency', 16)->nullable();
            $table->string('abbreviation', 2)->unique();
            $table->string('icon_url')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('countries');
    }
};
