<?php

namespace App\Workforce;

use App\Enums\ShiftStatus;
use App\Models\Department;
use App\Models\Location;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\StaffMember;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;

final class ScheduleService
{
    public function create(User $actor, string $name, string $starts, string $ends, Carbon $from, ?Carbon $until, ?Department $department, ?Location $location): Shift
    {
        $start = Carbon::parse($from->toDateString().' '.$starts);
        $end = Carbon::parse($from->toDateString().' '.$ends);

        $shift = Shift::query()->create([
            'organization_id' => $actor->organization_id,
            'department_id' => $department?->getKey(),
            'location_id' => $location?->getKey(),
            'created_by' => $actor->getKey(),
            'name' => $name,
            'starts_at' => $starts,
            'ends_at' => $ends,
            'crosses_midnight' => $end->lessThanOrEqualTo($start),
            'effective_from' => $from->toDateString(),
            'effective_until' => $until?->toDateString(),
            'status' => ShiftStatus::Draft,
        ]);

        Audit::log('schedule.created', $shift, ['name' => $name], $actor);

        return $shift;
    }

    public function publish(User $actor, Shift $shift): void
    {
        $shift->update(['status' => ShiftStatus::Published]);
        Audit::log('schedule.published', $shift, [], $actor);

        $shift->load('assignments.staffMember.user');
        foreach ($shift->assignments as $assignment) {
            app(WorkforceNotifier::class)->schedulePublished($assignment);
        }
    }

    public function assign(User $actor, Shift $shift, StaffMember $staff, Carbon $date): ShiftAssignment
    {
        if ($shift->effective_until !== null && $date->toDateString() > $shift->effective_until->toDateString()) {
            throw new AttendanceException('outside_effective_dates', 'That date is outside the schedule\'s effective dates.');
        }

        if ($date->toDateString() < $shift->effective_from->toDateString()) {
            throw new AttendanceException('outside_effective_dates', 'That date is outside the schedule\'s effective dates.');
        }

        try {
            $assignment = ShiftAssignment::query()->create([
                'organization_id' => $shift->organization_id,
                'shift_id' => $shift->getKey(),
                'staff_member_id' => $staff->getKey(),
                'work_date' => $date->toDateString(),
            ]);
        } catch (UniqueConstraintViolationException) {
            throw new AttendanceException('schedule_conflict', 'This person already has a shift or lesson on that date.');
        }

        Audit::log('schedule.assigned', $assignment, ['work_date' => $date->toDateString()], $actor);

        if ($shift->status === ShiftStatus::Published) {
            app(WorkforceNotifier::class)->schedulePublished($assignment);
        }

        return $assignment;
    }
}
