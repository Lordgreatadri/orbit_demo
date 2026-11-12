<?php

namespace App\Reports;

use App\Models\User;
use App\Reports\Contracts\ReportProvider;
use App\Reports\Providers\AttendanceByDepartmentReport;
use App\Reports\Providers\AttendanceByPersonReport;
use App\Reports\Providers\AttendanceDetailReport;
use App\Reports\Providers\AttendanceSummaryReport;
use App\Reports\Providers\IncompleteAttendanceReport;
use App\Reports\Providers\LeaveSummaryReport;
use App\Reports\Providers\NotificationDeliveryReport;
use App\Reports\Providers\PlatformOrganisationStatisticsReport;
use App\Reports\Providers\PunctualityReport;
use App\Reports\Providers\ScheduleCoverageReport;
use App\Reports\Providers\VerificationMethodsReport;
use InvalidArgumentException;

final class ReportRegistry
{
    /**
     * @param  list<class-string<ReportProvider>>  $providers
     */
    public function __construct(
        private array $providers = [
            AttendanceSummaryReport::class,
            AttendanceByPersonReport::class,
            AttendanceByDepartmentReport::class,
            AttendanceDetailReport::class,
            PunctualityReport::class,
            IncompleteAttendanceReport::class,
            VerificationMethodsReport::class,
            ScheduleCoverageReport::class,
            LeaveSummaryReport::class,
            NotificationDeliveryReport::class,
            PlatformOrganisationStatisticsReport::class,
        ],
    ) {}

    public function get(string $key): ReportProvider
    {
        foreach ($this->providers as $class) {
            $provider = app($class);
            if ($provider->definition()->key === $key) {
                return $provider;
            }
        }

        throw new InvalidArgumentException("Unknown report [{$key}].");
    }

    /**
     * @return list<ReportProvider>
     */
    public function for(User $user, ReportAudience $audience): array
    {
        $visible = [];

        foreach ($this->providers as $class) {
            $provider = app($class);
            $definition = $provider->definition();

            if ($definition->audience !== $audience) {
                continue;
            }

            if (! $provider->authorize($user)) {
                continue;
            }

            $visible[] = $provider;
        }

        return $visible;
    }
}
