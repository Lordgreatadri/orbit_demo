<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed reference data required in every environment.
     * Demo/fictional tenant data is deliberately NOT seeded here (see Stage 3 plan).
     */
    public function run(): void
    {
        $this->call([
            CountryTableSeeder::class,
            RolesAndPermissionsSeeder::class,
        ]);
    }
}
