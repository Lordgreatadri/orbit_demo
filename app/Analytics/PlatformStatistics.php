<?php

namespace App\Analytics;

use App\Enums\OrganizationType;
use App\Enums\StaffStatus;
use App\Enums\UserStatus;
use App\Enums\WorkType;
use App\Models\Invitation;
use App\Models\LoginEvent;
use App\Models\Organization;
use App\Models\Scopes\OrganizationScope;
use App\Models\StaffMember;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Platform-wide statistics for the platform console. Every figure is an
 * aggregate over organisations, accounts, register rows, invitations, or
 * sign-in events. It never reads a tenant's attendance, leave, or schedules,
 * and it never returns a member's name (ADR-0016).
 */
final class PlatformStatistics
{
    public const MONTHS = 12;

    public const SIGN_IN_DAYS = 14;

    /**
     * @return array<string, mixed>
     */
    public function compose(): array
    {
        $organisationsByStatus = Organization::query()
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');
        $organisationsByType = Organization::query()
            ->select('type', DB::raw('count(*) as total'))
            ->groupBy('type')
            ->pluck('total', 'type');

        $accounts = User::query()
            ->selectRaw("CASE WHEN organization_id IS NULL THEN 'platform' ELSE 'tenant' END as kind, status, count(*) as total, sum(CASE WHEN email_verified_at IS NULL THEN 0 ELSE 1 END) as verified")
            ->groupByRaw("CASE WHEN organization_id IS NULL THEN 'platform' ELSE 'tenant' END, status")
            ->get();

        $staffByStatus = StaffMember::withoutTenantScope()
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');
        $staffByWorkType = StaffMember::withoutTenantScope()
            ->where('status', StaffStatus::Active->value)
            ->select('work_type', DB::raw('count(*) as total'))
            ->groupBy('work_type')
            ->pluck('total', 'work_type');

        $since = now()->subDays(30);
        $signedInAccounts = LoginEvent::query()
            ->where('event', 'login')
            ->where('created_at', '>=', $since)
            ->whereNotNull('user_id')
            ->distinct()
            ->count('user_id');

        $tenantAccounts = (int) $accounts->where('kind', 'tenant')->sum('total');
        $activeTenantAccounts = (int) $accounts->where('kind', 'tenant')->filter(fn ($row) => $this->value($row->status) === UserStatus::Active->value)->sum('total');

        return [
            'organisations' => [
                'total' => (int) $organisationsByStatus->sum(),
                'by_status' => $this->organisationStatusRows($organisationsByStatus),
                'by_type' => $this->organisationTypeRows($organisationsByType),
            ],
            'accounts' => [
                'tenant' => $tenantAccounts,
                'tenant_active' => $activeTenantAccounts,
                'platform' => (int) $accounts->where('kind', 'platform')->sum('total'),
                'suspended' => (int) $accounts->filter(fn ($row) => $this->value($row->status) === UserStatus::Suspended->value)->sum('total'),
                'verified' => (int) $accounts->sum('verified'),
                'total' => (int) $accounts->sum('total'),
                'signed_in_30d' => $signedInAccounts,
            ],
            'staff' => [
                'total' => (int) $staffByStatus->sum(),
                'active' => (int) ($staffByStatus[StaffStatus::Active->value] ?? 0),
                'by_status' => $this->staffStatusRows($staffByStatus),
                'by_work_type' => $this->workTypeRows($staffByWorkType),
            ],
            'invitations' => $this->invitations(),
            'growth' => $this->growth(),
            'sign_ins' => $this->signIns(),
            'largest' => $this->largestOrganisations(),
        ];
    }

    /**
     * @return array{issued: int, accepted: int, pending: int, expired: int, revoked: int, acceptance: ?int}
     */
    private function invitations(): array
    {
        $now = now();
        $row = Invitation::withoutTenantScope()
            ->selectRaw('count(*) as issued')
            ->selectRaw('sum(CASE WHEN accepted_at IS NOT NULL THEN 1 ELSE 0 END) as accepted')
            ->selectRaw('sum(CASE WHEN accepted_at IS NULL AND revoked_at IS NOT NULL THEN 1 ELSE 0 END) as revoked')
            ->selectRaw('sum(CASE WHEN accepted_at IS NULL AND revoked_at IS NULL AND expires_at <= ? THEN 1 ELSE 0 END) as expired', [$now])
            ->selectRaw('sum(CASE WHEN accepted_at IS NULL AND revoked_at IS NULL AND expires_at > ? THEN 1 ELSE 0 END) as pending', [$now])
            ->first();

        $issued = (int) ($row->issued ?? 0);
        $accepted = (int) ($row->accepted ?? 0);
        $decided = $issued - (int) ($row->pending ?? 0);

        return [
            'issued' => $issued,
            'accepted' => $accepted,
            'pending' => (int) ($row->pending ?? 0),
            'expired' => (int) ($row->expired ?? 0),
            'revoked' => (int) ($row->revoked ?? 0),
            'acceptance' => $decided > 0 ? (int) round($accepted / $decided * 100) : null,
        ];
    }

    /**
     * New organisations and new accounts per calendar month, oldest first.
     *
     * @return array{organisations: list<array<string, mixed>>, accounts: list<array<string, mixed>>, organisations_total: int, accounts_total: int}
     */
    private function growth(): array
    {
        $start = now()->startOfMonth()->subMonths(self::MONTHS - 1);
        $month = $this->bucket('month');

        $organisations = Organization::query()
            ->where('created_at', '>=', $start)
            ->selectRaw("{$month} as bucket, count(*) as total")
            ->groupByRaw($month)
            ->pluck('total', 'bucket');
        $accounts = User::query()
            ->whereNotNull('organization_id')
            ->where('created_at', '>=', $start)
            ->selectRaw("{$month} as bucket, count(*) as total")
            ->groupByRaw($month)
            ->pluck('total', 'bucket');

        $rows = fn (Collection $counts, string $ink): array => collect(range(0, self::MONTHS - 1))
            ->map(function (int $offset) use ($start, $counts, $ink): array {
                $date = $start->copy()->addMonths($offset);

                return [
                    'key' => $date->format('Y-m'),
                    'label' => $date->format('M'),
                    'total' => (int) ($counts[$date->format('Y-m')] ?? 0),
                    'ink' => $ink,
                ];
            })->all();

        return [
            'organisations' => $rows($organisations, 'var(--primary)'),
            'accounts' => $rows($accounts, 'var(--accent)'),
            'organisations_total' => (int) $organisations->sum(),
            'accounts_total' => (int) $accounts->sum(),
        ];
    }

    /**
     * @return array{days: list<array<string, mixed>>, login: int, failed: int, lockout: int, success: ?int}
     */
    private function signIns(): array
    {
        $start = now()->startOfDay()->subDays(self::SIGN_IN_DAYS - 1);
        $day = $this->bucket('day');

        $rows = LoginEvent::query()
            ->where('created_at', '>=', $start)
            ->whereIn('event', ['login', 'failed', 'lockout'])
            ->selectRaw("{$day} as bucket, event, count(*) as total")
            ->groupByRaw("{$day}, event")
            ->get();

        $days = [];
        for ($offset = 0; $offset < self::SIGN_IN_DAYS; $offset++) {
            $date = $start->copy()->addDays($offset)->toDateString();
            $days[$date] = ['date' => $date, 'login' => 0, 'failed' => 0, 'lockout' => 0];
        }

        foreach ($rows as $row) {
            $key = substr((string) $row->bucket, 0, 10);
            $event = $this->value($row->event);
            if (isset($days[$key][$event])) {
                $days[$key][$event] += (int) $row->total;
            }
        }

        $login = (int) collect($days)->sum('login');
        $failed = (int) collect($days)->sum('failed');

        return [
            'days' => array_values($days),
            'login' => $login,
            'failed' => $failed,
            'lockout' => (int) collect($days)->sum('lockout'),
            'success' => ($login + $failed) > 0 ? (int) round($login / ($login + $failed) * 100) : null,
        ];
    }

    /**
     * @return Collection<int, Organization>
     */
    private function largestOrganisations(): Collection
    {
        return Organization::query()
            ->select(['id', 'uuid', 'name', 'type', 'status', 'created_at'])
            ->withCount([
                'users',
                'staffMembers as staff_count' => fn ($query) => $query->withoutGlobalScope(OrganizationScope::class),
                'staffMembers as active_staff_count' => fn ($query) => $query->withoutGlobalScope(OrganizationScope::class)->where('status', StaffStatus::Active->value),
            ])
            ->orderByDesc('staff_count')
            ->orderBy('name')
            ->limit(8)
            ->get();
    }

    /**
     * @param  Collection<string, int|string>  $counts
     * @return list<array{key: string, label: string, total: int, ink: string}>
     */
    private function organisationStatusRows(Collection $counts): array
    {
        return app(PlatformPulse::class)->statusMix($counts);
    }

    /**
     * @param  Collection<string, int|string>  $counts
     * @return list<array{key: string, label: string, total: int, ink: string}>
     */
    private function organisationTypeRows(Collection $counts): array
    {
        $inks = [
            OrganizationType::PublicInstitution->value => 'var(--primary)',
            OrganizationType::EducationalInstitution->value => 'var(--accent)',
            OrganizationType::Business->value => 'var(--brand-teal)',
            OrganizationType::Ngo->value => 'var(--info)',
            OrganizationType::Other->value => 'var(--fg-subtle)',
        ];

        return array_map(fn (OrganizationType $type): array => [
            'key' => $type->value,
            'label' => $type->label(),
            'total' => (int) ($counts[$type->value] ?? 0),
            'ink' => $inks[$type->value] ?? 'var(--fg-subtle)',
        ], OrganizationType::cases());
    }

    /**
     * @param  Collection<string, int|string>  $counts
     * @return list<array{key: string, label: string, total: int, ink: string}>
     */
    private function staffStatusRows(Collection $counts): array
    {
        return array_map(fn (StaffStatus $status): array => [
            'key' => $status->value,
            'label' => $status->label(),
            'total' => (int) ($counts[$status->value] ?? 0),
            'ink' => match ($status) {
                StaffStatus::Active => 'var(--success)',
                StaffStatus::OnLeave => 'var(--accent)',
                default => 'var(--fg-subtle)',
            },
        ], StaffStatus::cases());
    }

    /**
     * @param  Collection<string, int|string>  $counts
     * @return list<array{key: string, label: string, total: int, ink: string}>
     */
    private function workTypeRows(Collection $counts): array
    {
        return array_map(fn (WorkType $type): array => [
            'key' => $type->value,
            'label' => $type->label(),
            'total' => (int) ($counts[$type->value] ?? 0),
            'ink' => match ($type) {
                WorkType::Office => 'var(--primary)',
                WorkType::Field => 'var(--accent)',
                WorkType::Remote => 'var(--brand-teal)',
                WorkType::Hybrid => 'var(--fg-subtle)',
            },
        ], WorkType::cases());
    }

    private function bucket(string $size): string
    {
        $driver = DB::connection()->getDriverName();

        if ($size === 'month') {
            return match ($driver) {
                'pgsql' => "to_char(created_at AT TIME ZONE 'UTC', 'YYYY-MM')",
                'mysql', 'mariadb' => "DATE_FORMAT(created_at, '%Y-%m')",
                default => "strftime('%Y-%m', created_at)",
            };
        }

        return match ($driver) {
            'pgsql' => "to_char(created_at AT TIME ZONE 'UTC', 'YYYY-MM-DD')",
            'mysql', 'mariadb' => 'DATE(created_at)',
            default => 'date(created_at)',
        };
    }

    private function value(mixed $enum): string
    {
        return $enum instanceof \BackedEnum ? (string) $enum->value : (string) $enum;
    }
}
