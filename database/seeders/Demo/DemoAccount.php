<?php

namespace Database\Seeders\Demo;

use App\Enums\OrganizationStatus;
use App\Enums\UserStatus;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Idempotent upserts for fictional demo organisations and accounts.
 */
class DemoAccount
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function organization(string $slug, array $attributes): Organization
    {
        DemoGuard::assertAllowed();

        $existing = Organization::query()->where('slug', $slug)->first();

        return Organization::query()->updateOrCreate(
            ['slug' => $slug],
            array_merge([
                'timezone' => config('orbit.default_timezone'),
                'country_code' => config('orbit.default_country'),
                'status' => OrganizationStatus::Active,
            ], $attributes, [
                'status' => OrganizationStatus::Active,
                'verified_at' => $existing?->verified_at ?? now(),
            ]),
        );
    }

    /**
     * @param  array{first_name: string, last_name: string, email: string, phone: string}  $attributes
     */
    public static function user(array $attributes, string $role, ?Organization $organization = null): User
    {
        DemoGuard::assertAllowed();

        $email = Str::lower($attributes['email']);
        $existing = User::query()->where('email', $email)->first();

        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'organization_id' => $organization?->getKey(),
                'first_name' => $attributes['first_name'],
                'last_name' => $attributes['last_name'],
                'phone' => $attributes['phone'],
                'status' => UserStatus::Active,
                'terms_accepted_at' => $existing?->terms_accepted_at ?? now(),
                'terms_version' => $existing?->terms_version ?? config('orbit.terms_version'),
                'password' => DemoGuard::password(),
            ],
        );

        $user->forceFill([
            'email_verified_at' => $user->email_verified_at ?? now(),
            'phone_verified_at' => $user->phone_verified_at ?? now(),
        ])->save();

        $user->syncRoles([$role]);

        return $user;
    }
}
