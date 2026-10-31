<?php

namespace Database\Seeders;

use App\Actions\Tenancy\ProvisionOrganizationWorkspace;
use App\Models\Organization;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/**
 * Idempotent: provisions a primary hostname, branding row, and storage
 * directory for every organisation that predates hostname tenancy.
 * Safe to re-run. Does not write from a migration.
 */
class OrganizationWorkspaceBackfillSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('organizations')
            || ! Schema::hasTable('organization_domains')
            || ! Schema::hasTable('organization_brandings')) {
            return;
        }

        $provision = app(ProvisionOrganizationWorkspace::class);

        Organization::query()->orderBy('id')->each(function (Organization $organization) use ($provision): void {
            $provision->handle($organization);
        });
    }
}
