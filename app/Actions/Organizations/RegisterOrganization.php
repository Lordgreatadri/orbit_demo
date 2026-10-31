<?php

namespace App\Actions\Organizations;

use App\Actions\Tenancy\ProvisionOrganizationWorkspace;
use App\Enums\OrganizationStatus;
use App\Enums\UserStatus;
use App\Models\Organization;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;

/**
 * Public registration: creates the organisation (tenant) and its responsible
 * contact as the initial organisation administrator in one transaction (ADR-0005).
 */
class RegisterOrganization
{
    /**
     * @param  array{
     *     organization_name: string,
     *     organization_type: string,
     *     timezone: string,
     *     first_name: string,
     *     last_name: string,
     *     email: string,
     *     phone: string,
     *     password: string,
     * }  $data
     */
    public function handle(array $data, ?string $ipAddress = null): User
    {
        return DB::transaction(function () use ($data, $ipAddress): User {
            $requiresVerification = (bool) config('orbit.require_email_verification');

            $organization = Organization::create([
                'name' => $data['organization_name'],
                'slug' => Organization::uniqueSlugFor($data['organization_name']),
                'type' => $data['organization_type'],
                'status' => $requiresVerification ? OrganizationStatus::PendingVerification : OrganizationStatus::Active,
                'timezone' => $data['timezone'],
                'country_code' => config('orbit.default_country'),
                'contact_email' => $data['email'],
                'contact_phone' => $data['phone'],
                'verified_at' => $requiresVerification ? null : now(),
            ]);

            $user = User::create([
                'organization_id' => $organization->getKey(),
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'password' => $data['password'],
                'status' => UserStatus::Active,
                'terms_accepted_at' => now(),
                'terms_version' => config('orbit.terms_version'),
            ]);

            $user->assignRole('organization_admin');

            app(ProvisionOrganizationWorkspace::class)->handle($organization, $user->getKey());

            Audit::log(
                event: 'organization.registered',
                subject: $organization,
                properties: [
                    'organization_type' => $organization->type->value,
                    'ip' => $ipAddress,
                ],
                causer: $user,
                organizationId: $organization->getKey(),
            );

            return $user;
        });
    }
}
