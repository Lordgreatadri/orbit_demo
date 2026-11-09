<?php

namespace App\Workforce;

use App\Enums\ShiftStatus;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\StaffMember;
use App\Support\CalendarDay;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Resolves the published work period that attendance should treat as expected.
 * It does not decide present, late, or absent.
 */
final class ScheduleResolver
{
    /**
     * @return Collection<int, ShiftAssignment>
     */
    public function publishedOn(StaffMember $staff, string $date): Collection
    {
        return CalendarDay::constrain(
            ShiftAssignment::query()->where('staff_member_id', $staff->getKey()),
            'work_date',
            $date,
        )
            ->whereHas('shift', fn ($query) => $query->where('status', ShiftStatus::Published->value))
            ->with(['shift.arrangement', 'shift.location', 'arrangement', 'location'])
            ->orderBy('id')
            ->get();
    }

    public function periodAt(StaffMember $staff, CarbonInterface $at): ?ShiftAssignment
    {
        $tz = $this->timezone($staff);
        $local = $at->copy()->timezone($tz);
        $today = $local->toDateString();
        $yesterday = $local->copy()->subDay()->toDateString();

        $rows = $this->publishedOn($staff, $yesterday)->concat($this->publishedOn($staff, $today));

        $containing = $rows->first(function (ShiftAssignment $assignment) use ($local, $tz): bool {
            if ($assignment->shift === null) {
                return false;
            }

            [$start, $end] = $this->bounds($assignment->work_date->toDateString(), $assignment->shift, $tz, $assignment);

            return $local->greaterThanOrEqualTo($start) && $local->lessThan($end);
        });

        if ($containing !== null) {
            return $containing;
        }

        $todays = $rows->filter(fn (ShiftAssignment $assignment): bool => $assignment->work_date->toDateString() === $today && $assignment->shift !== null);

        $started = $todays
            ->filter(function (ShiftAssignment $assignment) use ($local, $tz): bool {
                [$start] = $this->bounds($assignment->work_date->toDateString(), $assignment->shift, $tz, $assignment);

                return $start->lessThanOrEqualTo($local);
            })
            ->sortByDesc(fn (ShiftAssignment $assignment): string => (string) ($assignment->starts_at ?: $assignment->shift->starts_at))
            ->first();

        return $started ?? $todays->sortBy(fn (ShiftAssignment $assignment): string => (string) ($assignment->starts_at ?: $assignment->shift->starts_at))->first();
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public function bounds(string $date, Shift $shift, string $timezone, ?ShiftAssignment $assignment = null): array
    {
        $starts = $assignment?->starts_at ?: $shift->starts_at;
        $ends = $assignment?->ends_at ?: $shift->ends_at;
        $crosses = $assignment?->starts_at !== null
            ? (bool) $assignment->crosses_midnight
            : (bool) $shift->crosses_midnight;
        $start = Carbon::parse($date.' '.$this->clock($starts), $timezone);
        $end = Carbon::parse($date.' '.$this->clock($ends), $timezone);

        if ($crosses || $end->lessThanOrEqualTo($start)) {
            $end->addDay();
        }

        return [$start, $end];
    }

    public function overlaps(string $date, Shift $shift, ShiftAssignment $other, string $timezone): bool
    {
        if ($other->shift === null) {
            return false;
        }

        [$start, $end] = $this->bounds($date, $shift, $timezone);
        [$otherStart, $otherEnd] = $this->bounds($other->work_date->toDateString(), $other->shift, $timezone, $other);

        return $start->lt($otherEnd) && $otherStart->lt($end);
    }

    private function timezone(StaffMember $staff): string
    {
        $staff->loadMissing('organization');

        return $staff->organization->timezone ?: 'UTC';
    }

    private function clock(mixed $value): string
    {
        $clock = (string) $value;

        return strlen($clock) === 5 ? $clock.':00' : $clock;
    }
}
