<?php

namespace App\Workforce;

use App\Enums\AttendanceStatus;
use App\Models\StaffMember;
use App\Models\WorkforcePolicy;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

final class Punctuality
{
    public function __construct(private readonly ScheduleResolver $schedules) {}

    /**
     * @return array{0: AttendanceStatus, 1: int}
     */
    public function checkIn(StaffMember $staff, WorkforcePolicy $policy, CarbonInterface $at): array
    {
        [$start, , $grace] = $this->window($staff, $policy, $at);
        $local = $at->copy()->timezone($this->timezone($staff));
        $deadline = $start->copy()->addMinutes($grace);

        if ($local->greaterThan($deadline)) {
            return [AttendanceStatus::Late, $this->minutes($deadline, $local)];
        }

        return [AttendanceStatus::Present, 0];
    }

    public function earlyLeaveMinutes(StaffMember $staff, WorkforcePolicy $policy, CarbonInterface $at): int
    {
        [, $end] = $this->window($staff, $policy, $at);
        $local = $at->copy()->timezone($this->timezone($staff));

        if ($local->greaterThanOrEqualTo($end)) {
            return 0;
        }

        return $this->minutes($local, $end);
    }

    /**
     * @return array{0: Carbon, 1: Carbon, 2: int}
     */
    public function window(StaffMember $staff, WorkforcePolicy $policy, CarbonInterface $at): array
    {
        $tz = $this->timezone($staff);
        $period = $this->schedules->periodAt($staff, $at);

        if ($period?->shift) {
            [$start, $end] = $this->schedules->bounds($period->work_date->toDateString(), $period->shift, $tz, $period);
        } else {
            $local = $at->copy()->timezone($tz);
            $date = $local->toDateString();
            $start = Carbon::parse($date.' '.$policy->work_start, $tz);
            $end = Carbon::parse($date.' '.$policy->work_end, $tz);
            if ($end->lessThanOrEqualTo($start)) {
                $end->addDay();
            }
        }

        return [$start, $end, (int) $policy->grace_minutes];
    }

    private function timezone(StaffMember $staff): string
    {
        $staff->loadMissing('organization');

        return $staff->organization->timezone ?: 'UTC';
    }

    private function minutes(CarbonInterface $from, CarbonInterface $to): int
    {
        return max(0, (int) round(abs($from->diffInMinutes($to))));
    }
}
