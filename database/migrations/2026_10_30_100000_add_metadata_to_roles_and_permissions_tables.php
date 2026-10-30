<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Roles carry a scope (platform|tenant) so the platform/tenant boundary
     * (ADR-0009 §5) is visible in data as well as in config, and both roles and
     * permissions carry human-readable metadata for the management screens.
     * `is_system` rows come from config/orbit.php and cannot be deleted.
     */
    public function up(): void
    {
        $tables = config('permission.table_names');

        Schema::table($tables['roles'], function (Blueprint $table): void {
            $table->string('scope', 16)->default('tenant')->after('name')->index();
            $table->string('description', 255)->nullable()->after('guard_name');
            $table->boolean('is_system')->default(false)->after('description');
        });

        Schema::table($tables['permissions'], function (Blueprint $table): void {
            $table->string('module', 64)->nullable()->after('name')->index();
            $table->string('description', 255)->nullable()->after('guard_name');
            $table->boolean('is_system')->default(false)->after('description');
        });

        // Everything that exists before this migration was seeded from config,
        // so the seeder may reconcile (and retire) it.
        DB::table($tables['roles'])->update(['is_system' => true]);
        DB::table($tables['permissions'])->update(['is_system' => true]);
        DB::table($tables['roles'])
            ->whereIn('name', array_keys(config('orbit.roles.platform', [])))
            ->update(['scope' => 'platform']);
    }

    public function down(): void
    {
        $tables = config('permission.table_names');

        Schema::table($tables['roles'], function (Blueprint $table): void {
            $table->dropIndex(['scope']);
            $table->dropColumn(['scope', 'description', 'is_system']);
        });

        Schema::table($tables['permissions'], function (Blueprint $table): void {
            $table->dropIndex(['module']);
            $table->dropColumn(['module', 'description', 'is_system']);
        });
    }
};
