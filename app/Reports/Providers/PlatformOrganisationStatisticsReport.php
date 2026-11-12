<?php

namespace App\Reports\Providers;

use App\Analytics\AnalyticsScope;
use App\Analytics\PlatformStatistics;
use App\Models\Organization;
use App\Models\User;
use App\Reports\ReportAudience;
use App\Reports\ReportDefinition;
use App\Reports\ReportFilters;
use App\Reports\ReportFormat;
use App\Reports\ReportKpi;
use App\Reports\ReportResult;
use Generator;
use Illuminate\Support\Carbon;

final class PlatformOrganisationStatisticsReport extends AbstractReportProvider
{
    public function __construct(private PlatformStatistics $statistics) {}

    public function definition(): ReportDefinition
    {
        return new ReportDefinition(
            key: 'platform-organisation-statistics',
            title: 'Platform organisation statistics',
            description: 'Platform-wide organisation, account, register, invitation, and sign-in aggregates. No tenant member names.',
            audience: ReportAudience::Platform,
            category: 'Platform',
            filters: [],
            formats: [ReportFormat::Csv, ReportFormat::Xlsx, ReportFormat::Pdf],
        );
    }

    public function authorize(User $user): bool
    {
        return $user->can('platform.access');
    }

    public function build(ReportFilters $filters, int $page = 1): ReportResult
    {
        $payload = $this->statistics->compose();
        $rows = $this->flatten($payload);

        return $this->result(
            filters: $filters,
            kpis: [
                new ReportKpi('Organisations', number_format($payload['organisations']['total']), icon: 'building'),
                new ReportKpi('Tenant accounts', number_format($payload['accounts']['tenant']), icon: 'users'),
                new ReportKpi('Active register', number_format((int) ($payload['staff']['active'] ?? 0)), icon: 'id-card'),
                new ReportKpi('Signed in (30d)', number_format((int) ($payload['accounts']['signed_in_30d'] ?? 0)), icon: 'login'),
            ],
            columns: $this->columns([
                ['key' => 'section', 'label' => 'Section'],
                ['key' => 'metric', 'label' => 'Metric'],
                ['key' => 'value', 'label' => 'Value'],
            ]),
            rows: $rows,
            emptyMessage: 'No platform statistics available.',
            findings: [
                'Aggregates only. Tenant attendance, leave, schedules, and member names are not included.',
            ],
            meta: ['payload' => $payload],
        );
    }

    public function exportRows(ReportFilters $filters): Generator
    {
        yield from $this->yieldRows($this->build($filters)->rowArrays());
    }

    /**
     * Platform reports still need a scope object for the shared result contract.
     */
    public static function platformFilters(User $user): ReportFilters
    {
        $organization = $user->organization ?? Organization::query()->first() ?? new Organization([
            'timezone' => 'UTC',
            'name' => 'Platform',
        ]);
        $today = Carbon::now($organization->timezone ?: 'UTC')->startOfDay();

        $scope = new AnalyticsScope(
            $organization,
            $user,
            AnalyticsScope::ORGANISATION,
            null,
            null,
            null,
            $today->copy()->subDays(30),
            $today,
        );

        return new ReportFilters(scope: $scope, rangeAdjusted: false);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array{section: string, metric: string, value: int|string}>
     */
    private function flatten(array $payload): array
    {
        $rows = [];

        foreach ($payload['organisations']['by_status'] ?? [] as $row) {
            $rows[] = ['section' => 'Organisations', 'metric' => $row['label'] ?? $row['key'] ?? 'Status', 'value' => $row['total']];
        }

        foreach ($payload['organisations']['by_type'] ?? [] as $row) {
            $rows[] = ['section' => 'Organisation types', 'metric' => $row['label'] ?? $row['key'] ?? 'Type', 'value' => $row['total']];
        }

        $rows[] = ['section' => 'Accounts', 'metric' => 'Tenant accounts', 'value' => $payload['accounts']['tenant'] ?? 0];
        $rows[] = ['section' => 'Accounts', 'metric' => 'Active tenant accounts', 'value' => $payload['accounts']['tenant_active'] ?? 0];
        $rows[] = ['section' => 'Accounts', 'metric' => 'Platform accounts', 'value' => $payload['accounts']['platform'] ?? 0];
        $rows[] = ['section' => 'Accounts', 'metric' => 'Suspended accounts', 'value' => $payload['accounts']['suspended'] ?? 0];
        $rows[] = ['section' => 'Accounts', 'metric' => 'Signed in (30 days)', 'value' => $payload['accounts']['signed_in_30d'] ?? 0];

        foreach ($payload['staff']['by_status'] ?? [] as $row) {
            $rows[] = ['section' => 'Register status', 'metric' => $row['label'] ?? $row['key'] ?? 'Status', 'value' => $row['total']];
        }

        foreach ($payload['staff']['by_work_type'] ?? [] as $row) {
            $rows[] = ['section' => 'Register work type', 'metric' => $row['label'] ?? $row['key'] ?? 'Work type', 'value' => $row['total']];
        }

        foreach (['issued', 'accepted', 'pending', 'expired', 'revoked'] as $key) {
            $rows[] = [
                'section' => 'Invitations',
                'metric' => ucfirst($key),
                'value' => $payload['invitations'][$key] ?? 0,
            ];
        }

        return $rows;
    }
}
