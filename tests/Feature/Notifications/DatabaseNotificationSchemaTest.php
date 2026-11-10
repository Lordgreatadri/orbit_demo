<?php

namespace Tests\Feature\Notifications;

use App\Models\DatabaseNotification;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DatabaseNotificationSchemaTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_database_notification_stores_a_numeric_id_and_the_channel_uuid(): void
    {
        $user = User::factory()->create();
        $channelId = (string) Str::uuid();

        $user->notifications()->create([
            'id' => $channelId,
            'type' => 'App\\Notifications\\WorkforceMessage',
            'data' => ['title' => 'Leave request submitted', 'body' => 'Dates recorded.', 'event' => 'leave.submitted'],
            'read_at' => null,
        ]);

        $stored = DB::table('notifications')->first();
        $this->assertNotNull($stored);
        $this->assertTrue(is_numeric($stored->id));
        $this->assertSame($channelId, $stored->uuid);
        $this->assertInstanceOf(DatabaseNotification::class, $user->notifications()->first());
    }

    #[Test]
    public function a_legacy_uuid_primary_key_is_rebuilt_into_the_numeric_id_and_uuid_column(): void
    {
        $user = User::factory()->create();
        $legacyId = (string) Str::uuid();

        Schema::disableForeignKeyConstraints();
        Schema::drop('notifications');
        Schema::enableForeignKeyConstraints();
        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        DB::table('notifications')->insert([
            'id' => $legacyId,
            'type' => 'App\\Notifications\\WorkforceMessage',
            'notifiable_type' => $user->getMorphClass(),
            'notifiable_id' => $user->getKey(),
            'data' => '{"title":"Kept"}',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = require base_path('database/migrations/2026_11_06_090000_align_notifications_table.php');
        $migration->up();

        $stored = DB::table('notifications')->first();
        $this->assertNotNull($stored);
        $this->assertTrue(is_numeric($stored->id));
        $this->assertSame($legacyId, $stored->uuid);
        $this->assertTrue(Schema::hasColumn('notifications', 'uuid'));

        $channelId = (string) Str::uuid();
        $user->notifications()->create([
            'id' => $channelId,
            'type' => 'App\\Notifications\\WorkforceMessage',
            'data' => ['title' => 'After rebuild'],
            'read_at' => null,
        ]);

        $this->assertSame($channelId, DB::table('notifications')->where('uuid', $channelId)->value('uuid'));
    }

    #[Test]
    public function inbox_column_rollback_drops_organization_id_with_or_without_its_foreign_key(): void
    {
        $migration = require base_path('database/migrations/2026_11_10_140500_add_organization_and_event_to_notifications.php');

        try {
            $this->assertTrue($this->organizationForeignKeyExists());
            $migration->down();
            $this->assertFalse(Schema::hasColumn('notifications', 'organization_id'));
            $this->assertFalse(Schema::hasColumn('notifications', 'event'));

            $migration->up();
            $this->assertTrue(Schema::hasColumn('notifications', 'organization_id'));
            $this->assertTrue($this->organizationForeignKeyExists());

            Schema::table('notifications', function (Blueprint $table): void {
                $table->dropForeign(['organization_id']);
            });
            $this->assertFalse($this->organizationForeignKeyExists());
            $this->assertTrue(Schema::hasColumn('notifications', 'organization_id'));

            $migration->down();
            $this->assertFalse(Schema::hasColumn('notifications', 'organization_id'));
            $this->assertFalse(Schema::hasColumn('notifications', 'event'));
        } finally {
            if (! Schema::hasColumn('notifications', 'organization_id') || ! $this->organizationForeignKeyExists()) {
                $migration->up();
            }
        }

        $this->assertTrue(Schema::hasColumn('notifications', 'organization_id'));
        $this->assertTrue(Schema::hasColumn('notifications', 'event'));
        $this->assertTrue($this->organizationForeignKeyExists());
    }

    private function organizationForeignKeyExists(): bool
    {
        if (! Schema::hasColumn('notifications', 'organization_id')) {
            return false;
        }

        return collect(Schema::getForeignKeys('notifications'))
            ->contains(fn (array $foreign): bool => in_array('organization_id', $foreign['columns'], true));
    }
}
