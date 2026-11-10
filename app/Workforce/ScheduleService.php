<?php

namespace App\Workforce;

use App\Enums\ShiftStatus;
use App\Enums\StaffStatus;
use App\Models\AttendanceRecord;
use App\Models\Department;
use App\Models\Location;
use App\Models\NonWorkingDay;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\StaffMember;
use App\Models\User;
use App\Models\WorkArrangement;
use App\Models\WorkPattern;
use App\Support\Audit;
use App\Support\CalendarDay;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class ScheduleService
{
    public function __construct(private readonly ScheduleResolver $resolver) {}

    public function create(
        User $actor,
        string $name,
        string $starts,
        string $ends,
        Carbon $from,
        ?Carbon $until,
        ?Department $department,
        ?Location $location,
        ?WorkArrangement $arrangement,
    ): Shift {
        $start = Carbon::parse($from->toDateString().' '.$starts);
        $end = Carbon::parse($from->toDateString().' '.$ends);

        $shift = Shift::query()->create([
            'organization_id' => $actor->organization_id,
            'department_id' => $department?->getKey(),
            'location_id' => $location?->getKey(),
            'work_arrangement_id' => $arrangement?->getKey(),
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

    public function assign(
        User $actor,
        Shift $shift,
        StaffMember $staff,
        Carbon $date,
        ?WorkArrangement $arrangement = null,
        ?Location $location = null,
        ?WorkPattern $pattern = null,
    ): ShiftAssignment {
        if ($shift->effective_until !== null && $date->toDateString() > $shift->effective_until->toDateString()) {
            throw new AttendanceException('outside_effective_dates', 'That date is outside the schedule\'s effective dates.');
        }

        if ($date->toDateString() < $shift->effective_from->toDateString()) {
            throw new AttendanceException('outside_effective_dates', 'That date is outside the schedule\'s effective dates.');
        }

        $timezone = $actor->organization->timezone ?: 'UTC';

        try {
            $assignment = ShiftAssignment::query()->getConnection()->transaction(function () use ($shift, $staff, $date, $arrangement, $location, $pattern, $timezone): ShiftAssignment {
                StaffMember::query()->whereKey($staff->getKey())->lockForUpdate()->first();
                $shift->loadMissing('arrangement', 'location');
                $this->assertNoOverlap($staff, $shift, $date->toDateString(), $timezone);

                return ShiftAssignment::query()->create([
                    'organization_id' => $shift->organization_id,
                    'shift_id' => $shift->getKey(),
                    'staff_member_id' => $staff->getKey(),
                    'work_arrangement_id' => ($arrangement ?? $shift->arrangement)?->getKey(),
                    'location_id' => ($location ?? $shift->location)?->getKey(),
                    'work_pattern_id' => $pattern?->getKey() ?? $shift->work_pattern_id,
                    'work_date' => $date->toDateString(),
                    'starts_at' => $shift->starts_at,
                    'ends_at' => $shift->ends_at,
                    'crosses_midnight' => (bool) $shift->crosses_midnight,
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            throw new AttendanceException('schedule_conflict', 'This person is already assigned to that shift on that date.');
        }

        Audit::log('schedule.assigned', $assignment, [
            'work_date' => $date->toDateString(),
            'arrangement' => $assignment->arrangement?->code,
        ], $actor);

        if ($shift->status === ShiftStatus::Published) {
            app(WorkforceNotifier::class)->schedulePublished($assignment);
        }

        return $assignment;
    }

    public function remove(User $actor, ShiftAssignment $assignment): void
    {
        $assignment->loadMissing('organization', 'shift');
        $timezone = $assignment->organization->timezone ?: ($actor->organization->timezone ?: 'UTC');
        $today = now($timezone)->toDateString();

        if ($assignment->work_date->toDateString() < $today) {
            throw new AttendanceException('schedule_closed', 'A past assignment stays on record so attendance can still be explained.');
        }

        DB::transaction(function () use ($actor, $assignment): void {
            StaffMember::query()->whereKey($assignment->staff_member_id)->lockForUpdate()->first();
            $locked = ShiftAssignment::query()->whereKey($assignment->getKey())->lockForUpdate()->first();

            if ($locked === null) {
                return;
            }

            if ($this->assignmentHasCheckIn($locked)) {
                throw new AttendanceException('schedule_closed', 'This assignment already has a check-in, so it stays on record.');
            }

            $published = $locked->shift?->status === ShiftStatus::Published;
            Audit::log('schedule.removed', $locked, ['work_date' => $locked->work_date?->toDateString()], $actor);
            $locked->delete();

            if ($published) {
                app(WorkforceNotifier::class)->scheduleRemoved($locked);
            }
        });
    }

    /**
     * Recheck inside the staff lock. A legacy day record counts when it can
     * only belong to this assignment.
     */
    private function assignmentHasCheckIn(ShiftAssignment $assignment): bool
    {
        $direct = AttendanceRecord::query()
            ->where('staff_member_id', $assignment->staff_member_id)
            ->where(function ($query) use ($assignment): void {
                $query->where('shift_assignment_id', $assignment->getKey())
                    ->orWhere('period_key', AttendanceRecord::shiftPeriod((int) $assignment->getKey()));
            })
            ->whereNotNull('check_in_at')
            ->lockForUpdate()
            ->exists();

        if ($direct) {
            return true;
        }

        $date = $assignment->work_date->toDateString();
        $legacy = AttendanceRecord::query()
            ->where('staff_member_id', $assignment->staff_member_id)
            ->where('period_key', AttendanceRecord::dayPeriod($date))
            ->whereNull('shift_assignment_id')
            ->whereNull('attendance_session_id')
            ->whereNotNull('check_in_at')
            ->lockForUpdate()
            ->first();

        if ($legacy === null) {
            return false;
        }

        $published = ShiftAssignment::query()
            ->where('staff_member_id', $assignment->staff_member_id)
            ->tap(fn ($query) => CalendarDay::constrain($query, 'work_date', $date))
            ->whereHas('shift', fn ($query) => $query->where('status', ShiftStatus::Published->value))
            ->with('shift')
            ->lockForUpdate()
            ->get();

        if ($published->count() === 1) {
            return (int) $published->first()->getKey() === (int) $assignment->getKey();
        }

        $assignment->loadMissing('organization', 'shift');
        $timezone = $assignment->organization->timezone ?: 'UTC';
        $at = $legacy->check_in_at->copy()->timezone($timezone);
        $containing = $published->filter(function (ShiftAssignment $candidate) use ($date, $timezone, $at): bool {
            if ($candidate->shift === null) {
                return false;
            }

            [$start, $end] = $this->resolver->bounds($date, $candidate->shift, $timezone, $candidate);

            return $at->greaterThanOrEqualTo($start) && $at->lessThan($end);
        });

        return $containing->count() === 1 && (int) $containing->first()->getKey() === (int) $assignment->getKey();
    }

    public function savePattern(
        User $actor,
        string $name,
        string $starts,
        string $ends,
        array $weekdays,
        Carbon $from,
        ?Carbon $until,
        ?Department $department,
        ?Location $location,
        ?WorkArrangement $arrangement,
    ): WorkPattern {
        $start = Carbon::parse($from->toDateString().' '.$starts);
        $end = Carbon::parse($from->toDateString().' '.$ends);

        $pattern = WorkPattern::query()->create([
            'organization_id' => $actor->organization_id,
            'department_id' => $department?->getKey(),
            'location_id' => $location?->getKey(),
            'work_arrangement_id' => $arrangement?->getKey(),
            'created_by' => $actor->getKey(),
            'name' => $name,
            'starts_at' => $starts,
            'ends_at' => $ends,
            'crosses_midnight' => $end->lessThanOrEqualTo($start),
            'weekdays' => array_values(array_unique(array_map('intval', $weekdays))),
            'effective_from' => $from->toDateString(),
            'effective_until' => $until?->toDateString(),
            'status' => ShiftStatus::Draft,
        ]);

        Audit::log('schedule.pattern_created', $pattern, ['name' => $name], $actor);

        return $pattern;
    }

    /**
     * Materialise a published pattern into shift assignments for up to 62 days.
     */
    public function applyPattern(User $actor, WorkPattern $pattern, Carbon $from, Carbon $until, ?Department $department): int
    {
        if ($from->diffInDays($until) > 62) {
            throw new AttendanceException('range_too_long', 'Apply a pattern to at most 62 days at a time.');
        }

        $pattern->loadMissing('arrangement', 'location');
        $shift = $this->shiftForPattern($actor, $pattern);

        if ($shift->status !== ShiftStatus::Published) {
            $this->publish($actor, $shift);
        }

        $pattern->update(['status' => ShiftStatus::Published]);

        $closed = NonWorkingDay::query()
            ->where('observed_on', '>=', $from->toDateString())
            ->where('observed_on', '<', CalendarDay::following($until->toDateString()))
            ->pluck('observed_on')
            ->map(fn ($day) => Carbon::parse($day)->toDateString())
            ->all();

        $dates = [];
        $cursor = $from->copy()->startOfDay();

        while ($cursor->lte($until)) {
            $iso = (int) $cursor->dayOfWeekIso;
            $key = $cursor->toDateString();

            if (in_array($iso, $pattern->weekdays ?? [], true) && ! in_array($key, $closed, true)) {
                $dates[] = $key;
            }

            $cursor->addDay();
        }

        $created = 0;

        StaffMember::query()
            ->where('status', StaffStatus::Active->value)
            ->when($department !== null, fn ($query) => $query->where('department_id', $department->getKey()))
            ->when($pattern->department_id !== null && $department === null, fn ($query) => $query->where('department_id', $pattern->department_id))
            ->when($pattern->staff_category_id !== null, fn ($query) => $query->where('staff_category_id', $pattern->staff_category_id))
            ->orderBy('id')
            ->chunkById(100, function ($people) use ($actor, $shift, $pattern, $dates, &$created): void {
                foreach ($people as $person) {
                    foreach ($dates as $key) {
                        try {
                            $this->assign($actor, $shift, $person, Carbon::parse($key), $pattern->arrangement, $pattern->location, $pattern);
                            $created++;
                        } catch (AttendanceException) {
                            continue;
                        }
                    }
                }
            });

        Audit::log('schedule.pattern_applied', $pattern, ['assignments' => $created], $actor);

        return $created;
    }

    public function markNonWorking(User $actor, Carbon $date, string $name, string $kind): NonWorkingDay
    {
        $day = CalendarDay::constrain(
            NonWorkingDay::query()->where('organization_id', $actor->organization_id),
            'observed_on',
            $date->toDateString(),
        )->first();

        if ($day === null) {
            $day = NonWorkingDay::query()->create([
                'organization_id' => $actor->organization_id,
                'observed_on' => $date->toDateString(),
                'name' => $name,
                'kind' => $kind,
            ]);
        } else {
            $day->update(['name' => $name, 'kind' => $kind]);
        }

        Audit::log('schedule.non_working', $day, ['date' => $date->toDateString()], $actor);

        return $day;
    }

    private function shiftForPattern(User $actor, WorkPattern $pattern): Shift
    {
        $existing = Shift::query()->where('work_pattern_id', $pattern->getKey())->first();

        if ($existing !== null) {
            $existing->update([
                'name' => $pattern->name,
                'starts_at' => $pattern->starts_at,
                'ends_at' => $pattern->ends_at,
                'crosses_midnight' => $pattern->crosses_midnight,
                'department_id' => $pattern->department_id,
                'location_id' => $pattern->location_id,
                'work_arrangement_id' => $pattern->work_arrangement_id,
                'effective_from' => $pattern->effective_from->toDateString(),
                'effective_until' => $pattern->effective_until?->toDateString(),
            ]);

            return $existing;
        }

        return Shift::query()->create([
            'organization_id' => $actor->organization_id,
            'department_id' => $pattern->department_id,
            'location_id' => $pattern->location_id,
            'work_pattern_id' => $pattern->getKey(),
            'work_arrangement_id' => $pattern->work_arrangement_id,
            'created_by' => $actor->getKey(),
            'name' => $pattern->name,
            'starts_at' => $pattern->starts_at,
            'ends_at' => $pattern->ends_at,
            'crosses_midnight' => $pattern->crosses_midnight,
            'effective_from' => $pattern->effective_from->toDateString(),
            'effective_until' => $pattern->effective_until?->toDateString(),
            'status' => ShiftStatus::Draft,
        ]);
    }

    private function assertNoOverlap(StaffMember $staff, Shift $shift, string $date, string $timezone): void
    {
        $nearby = ShiftAssignment::query()
            ->where('staff_member_id', $staff->getKey())
            ->where('work_date', '>=', Carbon::parse($date)->subDay()->toDateString())
            ->where('work_date', '<', CalendarDay::following(Carbon::parse($date)->addDay()->toDateString()))
            ->with('shift')
            ->lockForUpdate()
            ->get();

        foreach ($nearby as $existing) {
            if ($this->resolver->overlaps($date, $shift, $existing, $timezone)) {
                throw new AttendanceException('schedule_conflict', 'That time overlaps another shift already assigned on or beside that date.');
            }
        }
    }
}
