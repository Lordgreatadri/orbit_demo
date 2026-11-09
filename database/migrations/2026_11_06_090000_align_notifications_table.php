<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * The first notifications table used Laravel's default: `id` was the UUID
 * primary key. The application stores a numeric id and keeps that UUID in
 * `uuid`. Databases migrated before the model change still have the old
 * shape, so every database-channel insert fails with "column uuid does not
 * exist". This rebuilds that table and keeps each existing notification id.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notifications') || $this->aligned()) {
            return;
        }

        $hasUuid = Schema::hasColumn('notifications', 'uuid');
        $rows = DB::table('notifications')->get();

        Schema::drop('notifications');

        Schema::create('notifications', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['id', 'type']);
        });

        foreach ($rows as $row) {
            $uuid = $hasUuid && isset($row->uuid) && Str::isUuid((string) $row->uuid)
                ? (string) $row->uuid
                : (string) $row->id;

            if (! Str::isUuid($uuid)) {
                $uuid = (string) Str::uuid();
            }

            DB::table('notifications')->insert([
                'uuid' => $uuid,
                'type' => $row->type,
                'notifiable_type' => $row->notifiable_type,
                'notifiable_id' => $row->notifiable_id,
                'data' => $row->data,
                'read_at' => $row->read_at,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]);
        }
    }

    public function down(): void
    {
        // The numeric primary key is the supported shape. Reversing it would
        // put Laravel's notification UUID back into `id` and break inserts.
    }

    private function aligned(): bool
    {
        if (! Schema::hasColumn('notifications', 'uuid')) {
            return false;
        }

        $type = strtolower((string) Schema::getColumnType('notifications', 'id'));

        return in_array($type, ['integer', 'bigint', 'int', 'int4', 'int8'], true);
    }
};
