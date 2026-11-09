<?php

namespace App\Workforce;

use App\Enums\AttendanceStatus;
use App\Enums\ShiftStatus;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\StaffMember;
use App\Models\WorkforcePolicy;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

final class Punctuality
{
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
        $local = $at->copy()->timezone($tz);
        $date = $local->toDateString();

        $overnight = $this->publishedAssignment($staff, $local->copy()->subDay()->toDateString());
        if ($overnight?->shift) {
            [$start, $end] = $this->bounds($overnight->work_date->toDateString(), $overnight->shift, $tz);
            if ($local->greaterThanOrEqualTo($start) && $local->lessThan($end)) {
                return [$start, $end, (int) $policy->grace_minutes];
            }
        }

        $assignment = $this->publishedAssignment($staff, $date);

        if ($assignment?->shift) {
            [$start, $end] = $this->bounds($date, $assignment->shift, $tz);
        } else {
            $start = Carbon::parse($date.' '.$policy->work_start, $tz);
            $end = Carbon::parse($date.' '.$policy->work_end, $tz);
            if ($end->lessThanOrEqualTo($start)) {
                $end->addDay();
            }
        }

        return [$start, $end, (int) $policy->grace_minutes];
    }

    private function publishedAssignment(StaffMember $staff, string $date): ?ShiftAssignment
    {
        return ShiftAssignment::query()
            ->where('staff_member_id', $staff->getKey())
            ->whereDate('work_date', $date)
            ->whereHas('shift', fn ($query) => $query->where('status', ShiftStatus::Published->value))
            ->with('shift')
            ->first();
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function bounds(string $date, Shift $shift, string $tz): array
    {
        $start = Carbon::parse($date.' '.$shift->starts_at, $tz);
        $end = Carbon::parse($date.' '.$shift->ends_at, $tz);

        if ($shift->crosses_midnight || $end->lessThanOrEqualTo($start)) {
            $end->addDay();
        }

        return [$start, $end];
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
