<?php

namespace App\Auth;

use App\Contracts\SmsSender;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class PhoneVerificationCode
{
    public const TTL_MINUTES = 10;

    public const MAX_ATTEMPTS = 5;

    public function __construct(private readonly SmsSender $sms) {}

    public function issue(User $user): void
    {
        if ($user->phone === null || $user->phone === '') {
            throw ValidationException::withMessages([
                'phone' => __('Enter the phone number that should receive the code.'),
            ]);
        }

        $code = str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);

        Cache::put($this->cacheKey($user), [
            'hash' => hash_hmac('sha256', $code, (string) config('app.key')),
            'phone' => $user->phone,
            'attempts' => 0,
        ], now()->addMinutes(self::TTL_MINUTES));

        $this->sms->send(
            $user->phone,
            'ORBIT verification code: '.$code.'. It expires in '.self::TTL_MINUTES.' minutes.',
        );
    }

    public function confirm(User $user, string $code): void
    {
        $key = $this->cacheKey($user);
        $payload = Cache::get($key);

        if (! is_array($payload) || ($payload['phone'] ?? null) !== $user->phone) {
            throw ValidationException::withMessages([
                'code' => __('That code is invalid or has expired. Request a new one.'),
            ]);
        }

        $attempts = (int) ($payload['attempts'] ?? 0);

        if ($attempts >= self::MAX_ATTEMPTS) {
            Cache::forget($key);

            throw ValidationException::withMessages([
                'code' => __('Too many attempts. Request a new code.'),
            ]);
        }

        $expected = (string) ($payload['hash'] ?? '');
        $actual = hash_hmac('sha256', $code, (string) config('app.key'));

        if (! hash_equals($expected, $actual)) {
            $payload['attempts'] = $attempts + 1;
            Cache::put($key, $payload, now()->addMinutes(self::TTL_MINUTES));

            throw ValidationException::withMessages([
                'code' => __('That code is invalid or has expired. Request a new one.'),
            ]);
        }

        $user->forceFill(['phone_verified_at' => now()])->save();
        Cache::forget($key);

        Audit::log('auth.phone_verified', $user, [], $user);
    }

    private function cacheKey(User $user): string
    {
        return 'phone-verification:'.$user->getKey();
    }
}
