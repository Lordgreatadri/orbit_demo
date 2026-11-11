<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Supports organisation unread and read counts without scanning the inbox by owner.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notifications')) {
            return;
        }

        if (! Schema::hasIndex('notifications', 'notifications_organization_read')) {
            Schema::table('notifications', function (Blueprint $table): void {
                $table->index(['organization_id', 'read_at'], 'notifications_organization_read');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('notifications') || ! Schema::hasIndex('notifications', 'notifications_organization_read')) {
            return;
        }

        Schema::table('notifications', function (Blueprint $table): void {
            $table->dropIndex('notifications_organization_read');
        });
    }
};
