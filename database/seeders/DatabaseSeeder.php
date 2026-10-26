<?php

namespace Database\Seeders;

use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SystemDemoUserSeeder;
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
        ]);

        if(! app()->isProduction()) {
            $this->call([
                SystemDemoUserSeeder::class,
            ]);
        }
    }
}
