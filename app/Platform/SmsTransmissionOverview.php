<?php

namespace App\Platform;

use App\Enums\SmsMessageStatus;
use App\Models\SmsMessage;
use App\Models\SmsProvider;
use Illuminate\Support\Facades\DB;

/**
 * Seven-day transmission figures for the platform console.
 * Counts come from sms_messages. Acceptance is not treated as delivery.
 */
final class SmsTransmissionOverview
{
    /**
     * @return array{
     *     days: list<array{key: string, label: string, delivered: int, accepted: int, failed: int, other: int, total: int}>,
     *     totals: array{delivered: int, accepted: int, failed: int, logged: int, inflight: int, sent: int},
     *     today: array{delivered: int, failed: int, sent: int},
     *     rate: ?int,
     *     providers: list<array{provider: SmsProvider, delivered: int, accepted: int, failed: int, sent: int, bundle_low: bool, cash_low: bool}>,
     *     active: ?SmsProvider
     * }
     */
    public function __invoke(): array
    {
        $start = now()->startOfDay()->subDays(6);
        $todayKey = now()->toDateString();

        $days = [];
        for ($offset = 0; $offset < 7; $offset++) {
            $date = $start->copy()->addDays($offset);
            $days[$date->toDateString()] = [
                'key' => $date->toDateString(),
                'label' => $date->format('D'),
                'delivered' => 0,
                'accepted' => 0,
                'failed' => 0,
                'other' => 0,
                'total' => 0,
            ];
        }

        $totals = ['delivered' => 0, 'accepted' => 0, 'failed' => 0, 'logged' => 0, 'inflight' => 0, 'sent' => 0];
        $today = ['delivered' => 0, 'failed' => 0, 'sent' => 0];
        $byProvider = [];

        $day = match (DB::connection()->getDriverName()) {
            'pgsql' => "to_char(created_at AT TIME ZONE 'UTC', 'YYYY-MM-DD')",
            default => 'date(created_at)',
        };

        $rows = SmsMessage::query()
            ->where('created_at', '>=', $start)
            ->selectRaw("sms_provider_id, status, {$day} as day, count(*) as total")
            ->groupByRaw("sms_provider_id, status, {$day}")
            ->get();

        foreach ($rows as $row) {
            $count = (int) $row->total;
            $status = $row->status instanceof SmsMessageStatus
                ? $row->status
                : SmsMessageStatus::from((string) $row->status);
            $key = is_string($row->day) ? substr($row->day, 0, 10) : null;
            $bucket = $this->bucket($status);

            if ($key !== null && isset($days[$key])) {
                $days[$key][$bucket] += $count;
                $days[$key]['total'] += $count;
            }

            $totals[$this->totalKey($status)] += $count;
            if ($status !== SmsMessageStatus::Logged) {
                $totals['sent'] += $count;
            }

            if ($key === $todayKey) {
                if ($status === SmsMessageStatus::Delivered) {
                    $today['delivered'] += $count;
                }
                if ($status === SmsMessageStatus::Failed) {
                    $today['failed'] += $count;
                }
                if ($status !== SmsMessageStatus::Logged) {
                    $today['sent'] += $count;
                }
            }

            if ($row->sms_provider_id !== null && $status !== SmsMessageStatus::Logged) {
                $id = (int) $row->sms_provider_id;
                $byProvider[$id] ??= ['delivered' => 0, 'accepted' => 0, 'failed' => 0, 'sent' => 0];
                $byProvider[$id]['sent'] += $count;
                if ($status === SmsMessageStatus::Delivered) {
                    $byProvider[$id]['delivered'] += $count;
                } elseif ($status === SmsMessageStatus::Accepted) {
                    $byProvider[$id]['accepted'] += $count;
                } elseif ($status === SmsMessageStatus::Failed) {
                    $byProvider[$id]['failed'] += $count;
                }
            }
        }

        $answered = $totals['delivered'] + $totals['accepted'] + $totals['failed'];

        $providers = SmsProvider::query()
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get()
            ->map(function (SmsProvider $provider) use ($byProvider): array {
                $counts = $byProvider[$provider->id] ?? ['delivered' => 0, 'accepted' => 0, 'failed' => 0, 'sent' => 0];

                return [
                    'provider' => $provider,
                    'delivered' => $counts['delivered'],
                    'accepted' => $counts['accepted'],
                    'failed' => $counts['failed'],
                    'sent' => $counts['sent'],
                    'bundle_low' => $this->atOrBelow($provider->smsBundleReading(), $provider->low_bundle_threshold),
                    'cash_low' => $this->atOrBelow(
                        $provider->cash_balance !== null ? (string) $provider->cash_balance : null,
                        $provider->low_balance_threshold !== null ? (string) $provider->low_balance_threshold : null,
                    ),
                ];
            })
            ->all();

        return [
            'days' => array_values($days),
            'totals' => $totals,
            'today' => $today,
            'rate' => $answered === 0 ? null : (int) round($totals['delivered'] * 100 / $answered),
            'providers' => $providers,
            'active' => collect($providers)->first(fn (array $row) => $row['provider']->is_active && $row['provider']->enabled)['provider'] ?? null,
        ];
    }

    private function bucket(SmsMessageStatus $status): string
    {
        return match ($status) {
            SmsMessageStatus::Delivered => 'delivered',
            SmsMessageStatus::Accepted => 'accepted',
            SmsMessageStatus::Failed => 'failed',
            default => 'other',
        };
    }

    private function totalKey(SmsMessageStatus $status): string
    {
        return match ($status) {
            SmsMessageStatus::Delivered => 'delivered',
            SmsMessageStatus::Accepted => 'accepted',
            SmsMessageStatus::Failed => 'failed',
            SmsMessageStatus::Logged => 'logged',
            default => 'inflight',
        };
    }

    private function atOrBelow(?string $reading, int|string|null $threshold): bool
    {
        if ($reading === null || $threshold === null || $threshold === '') {
            return false;
        }

        return bccomp($reading, (string) $threshold, 2) <= 0;
    }
}
