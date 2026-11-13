<?php

namespace App\Reports\Providers;

use App\Analytics\AnalyticsScope;
use App\Analytics\OperationalDigest;
use App\Reports\ReportAudience;
use App\Reports\ReportDefinition;
use App\Reports\ReportFilters;
use App\Reports\ReportFormat;
use App\Reports\ReportKpi;
use App\Reports\ReportResult;
use Generator;

final class NotificationDeliveryReport extends AbstractReportProvider
{
    public function __construct(private OperationalDigest $digest) {}

    public function definition(): ReportDefinition
    {
        return new ReportDefinition(
            key: 'notification-delivery',
            title: 'Notification delivery',
            description: 'Organisation-wide notice delivery and inbox read totals.',
            audience: ReportAudience::Tenant,
            category: 'Notifications',
            filters: [],
            formats: [ReportFormat::Csv, ReportFormat::Xlsx, ReportFormat::Pdf],
            organisationPerspectiveOnly: AnalyticsScope::ORGANISATION,
        );
    }

    public function build(ReportFilters $filters, int $page = 1): ReportResult
    {
        $notifications = $this->digest->notifications($filters->scope);
        $rows = [];

        foreach ($notifications['deliveries'] ?? [] as $status => $total) {
            $rows[] = [
                'metric' => 'Delivery: '.$status,
                'value' => $total,
            ];
        }

        $rows[] = [
            'metric' => 'Inbox unread',
            'value' => $notifications['unread'],
        ];
        $rows[] = [
            'metric' => 'Inbox read',
            'value' => $notifications['read'],
        ];

        return $this->result(
            filters: $filters,
            kpis: [
                new ReportKpi('Unread', number_format($notifications['unread']), icon: 'bell', tone: 'warning'),
                new ReportKpi('Read', number_format((int) ($notifications['read'] ?? 0)), icon: 'check-circle', tone: 'success'),
                new ReportKpi('Deliveries logged', number_format(array_sum($notifications['deliveries'] ?? [])), icon: 'chart'),
            ],
            columns: $this->columns([
                ['key' => 'metric', 'label' => 'Metric'],
                ['key' => 'value', 'label' => 'Value'],
            ]),
            rows: $rows,
            emptyMessage: 'No notification delivery figures for this organisation.',
            findings: [
                'Delivery status and read status are separate operational signals.',
            ],
        );
    }

    public function exportRows(ReportFilters $filters): Generator
    {
        yield from $this->yieldRows($this->build($filters)->rowArrays());
    }
}
