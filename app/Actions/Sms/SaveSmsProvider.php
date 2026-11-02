<?php

namespace App\Actions\Sms;

use App\Integrations\Sms\SmsProviderRegistry;
use App\Models\SmsProvider;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveSmsProvider
{
    public function __construct(private readonly SmsProviderRegistry $registry) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function __invoke(SmsProvider $provider, array $input, User $actor): SmsProvider
    {
        $adapter = $this->registry->get($provider->key);
        $enabled = (bool) $input['enabled'];
        $active = (bool) $input['is_active'];
        $fallback = (bool) $input['is_fallback'];

        if ($active && ! $enabled) {
            throw ValidationException::withMessages([
                'is_active' => 'Turn the provider on before using it for delivery.',
            ]);
        }

        if ($active && $fallback) {
            throw ValidationException::withMessages([
                'is_fallback' => 'The provider that sends messages cannot also be the fallback.',
            ]);
        }

        $credentials = $provider->credentials ?? [];
        $incomingCredentials = $input['credentials'] ?? [];

        foreach ($adapter->credentialFields() as $field) {
            $incoming = $incomingCredentials[$field['key']] ?? null;

            if ($field['secret'] && ($incoming === null || $incoming === '')) {
                continue;
            }

            if (! $field['secret']) {
                $credentials[$field['key']] = is_string($incoming) ? trim($incoming) : '';

                continue;
            }

            $credentials[$field['key']] = $incoming;
        }

        $provider->fill([
            'sender_id' => isset($input['sender_id']) && $input['sender_id'] !== '' ? trim((string) $input['sender_id']) : null,
            'enabled' => $enabled,
            'is_active' => $active,
            'is_fallback' => $fallback,
            'sandbox' => $adapter->supportsSandbox() && (bool) ($input['sandbox'] ?? false),
            'timeout_seconds' => (int) $input['timeout_seconds'],
            'max_attempts' => (int) $input['max_attempts'],
            'requests_per_minute' => (int) $input['requests_per_minute'],
            'low_balance_threshold' => $adapter->tracksCashBalance()
                ? ((($input['low_balance_threshold'] ?? null) === null || $input['low_balance_threshold'] === '') ? null : $input['low_balance_threshold'])
                : $provider->low_balance_threshold,
            'low_bundle_threshold' => $adapter->tracksSmsBundle()
                ? ((($input['low_bundle_threshold'] ?? null) === null || $input['low_bundle_threshold'] === '') ? null : $input['low_bundle_threshold'])
                : $provider->low_bundle_threshold,
            'backoff_seconds' => array_map('intval', preg_split('/\s*,\s*/', (string) $input['backoff_seconds']) ?: []),
            'credentials' => $credentials,
        ]);

        if ($enabled) {
            $missing = [];

            if (! $provider->sender_id) {
                $missing[] = 'a sender ID';
            }

            foreach ($adapter->credentialFields() as $field) {
                if (! $provider->hasCredential($field['key'])) {
                    $missing[] = 'the '.$field['label'];
                }
            }

            if ($missing !== []) {
                throw ValidationException::withMessages([
                    'enabled' => 'Add '.implode(' and ', $missing).' before turning this provider on.',
                ]);
            }
        }

        DB::transaction(function () use ($provider, $active, $fallback): void {
            if ($active) {
                SmsProvider::query()->whereKeyNot($provider->id)->where('is_active', true)->update(['is_active' => false]);
            }

            if ($fallback) {
                SmsProvider::query()->whereKeyNot($provider->id)->where('is_fallback', true)->update(['is_fallback' => false]);
            }

            $provider->save();
        });

        Audit::log('sms_provider.updated', $provider, [
            'key' => $provider->key,
            'enabled' => $provider->enabled,
            'is_active' => $provider->is_active,
            'is_fallback' => $provider->is_fallback,
            'sender_id' => $provider->sender_id,
            'sandbox' => $provider->sandbox,
            'timeout_seconds' => $provider->timeout_seconds,
            'max_attempts' => $provider->max_attempts,
            'requests_per_minute' => $provider->requests_per_minute,
        ], $actor);

        return $provider;
    }
}
