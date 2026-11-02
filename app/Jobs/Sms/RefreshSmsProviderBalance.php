<?php

namespace App\Jobs\Sms;

use App\Enums\SmsProviderHealth;
use App\Integrations\Sms\BalanceSnapshot;
use App\Integrations\Sms\Exceptions\RetryableSmsException;
use App\Integrations\Sms\SmsLog;
use App\Integrations\Sms\SmsProviderRegistry;
use App\Integrations\Sms\SmsText;
use App\Models\SmsProvider;
use App\Models\User;
use App\Notifications\SmsBalanceLowNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;

class RefreshSmsProviderBalance implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public int $providerId) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 120];
    }

    public function handle(SmsProviderRegistry $registry): void
    {
        $provider = SmsProvider::query()->find($this->providerId);

        if ($provider === null || ! $provider->enabled) {
            return;
        }

        try {
            $snapshot = $registry->get($provider->key)->balance($provider);
        } catch (RetryableSmsException $exception) {
            $provider->forceFill([
                'health_status' => SmsProviderHealth::Degraded,
                'health_message' => SmsText::safe($exception->getMessage()),
                'health_checked_at' => now(),
            ])->save();

            SmsLog::provider($provider->key)->warning('SMS balance check did not complete.', [
                'provider' => $provider->key,
                'health' => $provider->health_status->value,
                'message' => $provider->health_message,
            ]);

            return;
        }

        $provider->forceFill([
            'health_status' => $snapshot->health,
            'health_message' => SmsText::safe($snapshot->message),
            'health_checked_at' => now(),
            'balance' => $snapshot->amount ?? $provider->balance,
            'balance_unit' => $snapshot->unit ?? $provider->balance_unit,
            'sms_bundle_balance' => $snapshot->bundleAmount !== null && is_numeric($snapshot->bundleAmount)
                ? (int) round((float) $snapshot->bundleAmount)
                : $provider->sms_bundle_balance,
            'cash_balance' => $snapshot->cashAmount ?? $provider->cash_balance,
            'cash_balance_unit' => $snapshot->cashUnit ?? $provider->cash_balance_unit,
            'balance_checked_at' => $snapshot->amount !== null || $snapshot->cashAmount !== null ? now() : $provider->balance_checked_at,
        ])->save();

        $provider->refresh();
        $reasons = $this->reasons($provider, $snapshot);

        SmsLog::provider($provider->key)->info('SMS balance refreshed.', [
            'provider' => $provider->key,
            'sms_bundle' => $snapshot->bundleAmount,
            'cash' => $snapshot->cashAmount,
            'cash_unit' => $snapshot->cashUnit,
            'bundle_threshold' => $provider->low_bundle_threshold,
            'cash_threshold' => $provider->low_balance_threshold,
            'warn' => $reasons !== [],
        ]);

        $this->notifyIfLow($provider, $reasons);
    }

    /**
     * A purse warns only when this reading is at or below its own threshold.
     * The SMS bundle is never compared with the cash threshold.
     *
     * @return list<array{purse: string, label: string, balance: string, unit: string, threshold: string}>
     */
    private function reasons(SmsProvider $provider, BalanceSnapshot $snapshot): array
    {
        $reasons = [];

        if ($snapshot->bundleAmount !== null && $provider->low_bundle_threshold !== null
            && bccomp((string) $snapshot->bundleAmount, (string) $provider->low_bundle_threshold, 2) <= 0) {
            $reasons[] = [
                'purse' => 'bundle',
                'label' => 'SMS bundle',
                'balance' => (string) $snapshot->bundleAmount,
                'unit' => $snapshot->bundleUnit ?: 'SMS',
                'threshold' => (string) $provider->low_bundle_threshold,
            ];
        }

        if ($snapshot->cashAmount !== null && $provider->low_balance_threshold !== null
            && bccomp((string) $snapshot->cashAmount, (string) $provider->low_balance_threshold, 2) <= 0) {
            $reasons[] = [
                'purse' => 'cash',
                'label' => 'Cash balance',
                'balance' => (string) $snapshot->cashAmount,
                'unit' => $snapshot->cashUnit ?: 'cash',
                'threshold' => (string) $provider->low_balance_threshold,
            ];
        }

        return $reasons;
    }

    /**
     * @param  list<array{purse: string, label: string, balance: string, unit: string, threshold: string}>  $reasons
     */
    private function notifyIfLow(SmsProvider $provider, array $reasons): void
    {
        if ($reasons === []) {
            if ($provider->low_balance_notified_at !== null) {
                $provider->forceFill(['low_balance_notified_at' => null])->save();
            }

            return;
        }

        if ($provider->low_balance_notified_at !== null && $provider->low_balance_notified_at->gt(now()->subDay())) {
            return;
        }

        $operators = User::query()->role('platform_super_admin')->get();

        if ($operators->isEmpty()) {
            return;
        }

        $provider->forceFill(['low_balance_notified_at' => now()])->save();
        Notification::send($operators, new SmsBalanceLowNotification($provider->name, $provider->uuid, $reasons));
    }
}
