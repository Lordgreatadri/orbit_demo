<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The package ships this schema as a stub. Own the table so it is created
     * with the rest of the application migrations.
     */
    public function up(): void
    {
        Schema::create('webhook_calls', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('url', 512);
            $table->json('headers')->nullable();
            $table->json('payload')->nullable();
            $table->json('attachments')->nullable();
            $table->text('exception')->nullable();
            $table->timestamps();

            $table->index(['name', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_calls');
    }
};
