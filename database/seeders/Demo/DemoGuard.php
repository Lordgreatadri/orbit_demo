<?php

namespace Database\Seeders\Demo;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use RuntimeException;

/**
 * Keeps fictional demo accounts out of production and out of source control.
 */
class DemoGuard
{
    public static function assertAllowed(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('SystemDemoUserSeeder cannot run when APP_ENV=production.');
        }
    }

    public static function password(): string
    {
        self::assertAllowed();

        $password = (string) config('orbit.demo_password');

        $validator = Validator::make(
            ['password' => $password],
            ['password' => ['required', 'string', Password::defaults()]],
        );

        if ($validator->fails()) {
            throw new RuntimeException(
                'Set ORBIT_DEMO_PASSWORD to a local password of at least 10 characters with letters and numbers. The demo seeder does not ship a password.'
            );
        }

        return $password;
    }
}
