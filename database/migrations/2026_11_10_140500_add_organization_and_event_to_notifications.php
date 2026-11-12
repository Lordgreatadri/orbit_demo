<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant and event columns on the user inbox. Both stay nullable.
 * `read_at` stays the unread/read flag: null is unread, a timestamp is read.
 *
 * This migration does not copy existing rows. A notice created after this
 * column exists stores its organisation and event itself. A notice that
 * already existed keeps a null until
 * `php artisan orbit:data:backfill-notification-inbox` copies the organisation
 * from that notice's user and the event from its JSON payload. Until then the
 * organisation inbox does not show that row, because the inbox requires
 * `organization_id` to match the signed-in user. The columns are not made
 * required: a notice can outlive its user, and the payload may not contain
 * an event.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notifications')) {
            return;
        }

        if (! Schema::hasColumn('notifications', 'organization_id')) {
            Schema::table('notifications', function (Blueprint $table): void {
                $table->foreignId('organization_id')->nullable()->after('uuid')->constrained('organizations')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('notifications', 'event')) {
            Schema::table('notifications', function (Blueprint $table): void {
                $table->string('event', 64)->nullable()->after('organization_id');
            });
        }

        if (! Schema::hasIndex('notifications', 'notifications_owner_unread')) {
            Schema::table('notifications', function (Blueprint $table): void {
                $table->index(['notifiable_type', 'notifiable_id', 'read_at'], 'notifications_owner_unread');
            });
        }

        if (! Schema::hasIndex('notifications', 'notifications_owner_event')) {
            Schema::table('notifications', function (Blueprint $table): void {
                $table->index(['notifiable_type', 'notifiable_id', 'event'], 'notifications_owner_event');
            });
        }

        if (! Schema::hasIndex('notifications', 'notifications_organization_read')) {
            Schema::table('notifications', function (Blueprint $table): void {
                $table->index(['organization_id', 'read_at'], 'notifications_organization_read');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('notifications')) {
            return;
        }

        if (Schema::hasIndex('notifications', 'notifications_owner_event')) {
            Schema::table('notifications', function (Blueprint $table): void {
                $table->dropIndex('notifications_owner_event');
            });
        }

        if (Schema::hasIndex('notifications', 'notifications_owner_unread')) {
            Schema::table('notifications', function (Blueprint $table): void {
                $table->dropIndex('notifications_owner_unread');
            });
        }

        if (Schema::hasColumn('notifications', 'event')) {
            Schema::table('notifications', function (Blueprint $table): void {
                $table->dropColumn('event');
            });
        }

        if (Schema::hasIndex('notifications', 'notifications_organization_read')) {
            Schema::table('notifications', function (Blueprint $table): void {
                $table->dropIndex('notifications_organization_read');
            });
        }

        if (Schema::hasColumn('notifications', 'organization_id')) {
            // The UUID-inbox rebuild stores this column without a foreign key.
            // Dropping a constraint that was never created fails on PostgreSQL and MySQL.
            // The organisation/read index is created here and again by a later
            // migration on databases that already applied this file, so either
            // rollback must drop it before the column.
            $hasForeignKey = collect(Schema::getForeignKeys('notifications'))
                ->contains(fn (array $foreign): bool => in_array('organization_id', $foreign['columns'], true));

            Schema::table('notifications', function (Blueprint $table) use ($hasForeignKey): void {
                if ($hasForeignKey) {
                    $table->dropConstrainedForeignId('organization_id');
                } else {
                    $table->dropColumn('organization_id');
                }
            });
        }
    }
};
