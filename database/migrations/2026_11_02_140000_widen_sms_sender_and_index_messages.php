<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite rebuilds the whole table for a column change and drops the
        // partial unique indexes that allow many inactive providers. Fresh
        // installs already create these columns at 34 characters. PostgreSQL
        // can widen the existing column in place.
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            Schema::table('sms_providers', function (Blueprint $table): void {
                $table->string('sender_id', 34)->nullable()->change();
            });

            Schema::table('sms_messages', function (Blueprint $table): void {
                $table->string('sender_id', 34)->nullable()->change();
            });
        }

        if (! Schema::hasIndex('sms_messages', ['created_at'])) {
            Schema::table('sms_messages', function (Blueprint $table): void {
                $table->index('created_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('sms_messages', ['created_at'])) {
            Schema::table('sms_messages', function (Blueprint $table): void {
                $table->dropIndex(['created_at']);
            });
        }

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('sms_providers', function (Blueprint $table): void {
            $table->string('sender_id', 20)->nullable()->change();
        });

        Schema::table('sms_messages', function (Blueprint $table): void {
            $table->string('sender_id', 20)->nullable()->change();
        });
    }
};
