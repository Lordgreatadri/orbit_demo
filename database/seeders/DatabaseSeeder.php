<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed reference data required in every environment.
     * Fictional demo tenants are separate: php artisan db:seed --class=SystemDemoUserSeeder
     */
    public function run(): void
    {
        $this->call([
            CountryTableSeeder::class,
            RolesAndPermissionsSeeder::class,
            OrganizationWorkspaceBackfillSeeder::class,
        ]);

        if (! app()->isProduction() && filled(config('orbit.demo_password'))) {
            $this->call([
                SystemDemoUserSeeder::class,
            ]);
        }
    }
}
