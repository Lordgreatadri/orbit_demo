<?php

namespace App\Workforce;

use App\Enums\AttendanceStatus;
use App\Enums\StaffStatus;
use App\Enums\VerificationMethod;
use App\Models\AttendanceEvent;
use App\Models\AttendanceRecord;
use App\Models\LeaveRequest;
use App\Models\Organization;
use App\Models\ShiftAssignment;
use App\Models\StaffMember;
use App\Support\CalendarDay;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Tenant attendance figures from stored rows. Aggregates stay in SQL.
 */
final class WorkforceReport
{
    /**
     * @return array{present: int, late: int, absent: int, on_leave: int, incomplete: int, excused: int, active_staff: int}
     */
    public function day(Organization $organization, ?string $date = null): array
    {
        $date ??= now($organization->timezone ?: 'UTC')->toDateString();

        $counts = CalendarDay::constrain(AttendanceRecord::query(), 'work_date', $date)
            ->select('status', DB::raw('count(distinct staff_member_id) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $active = StaffMember::query()->where('status', StaffStatus::Active->value)->count();
        $covered = (int) CalendarDay::constrain(AttendanceRecord::query(), 'work_date', $date)
            ->whereIn('status', [
                AttendanceStatus::Present->value,
                AttendanceStatus::Late->value,
                AttendanceStatus::OnLeave->value,
                AttendanceStatus::Incomplete->value,
                AttendanceStatus::Excused->value,
            ])
            ->distinct()
            ->count('staff_member_id');

        return [
            'present' => (int) ($counts[AttendanceStatus::Present->value] ?? 0),
            'late' => (int) ($counts[AttendanceStatus::Late->value] ?? 0),
            'on_leave' => (int) ($counts[AttendanceStatus::OnLeave->value] ?? 0),
            'incomplete' => (int) ($counts[AttendanceStatus::Incomplete->value] ?? 0),
            'excused' => (int) ($counts[AttendanceStatus::Excused->value] ?? 0),
            'absent' => max(0, $active - $covered),
            'active_staff' => $active,
        ];
    }

    /**
     * @return list<array{date: string, counts: array<string, int>, present: int, late: int, on_leave: int, other: int}>
     */
    public function trend(Organization $organization, Carbon $from, Carbon $to): array
    {
        $rows = AttendanceRecord::query()
            ->where('work_date', '>=', $from->toDateString())
            ->where('work_date', '<', CalendarDay::following($to->toDateString()))
            ->select('work_date', 'status', DB::raw('count(*) as total'))
            ->groupBy('work_date', 'status')
            ->get();

        $byDate = [];
        foreach ($rows as $row) {
            $date = Carbon::parse($row->work_date)->toDateString();
            $byDate[$date][$row->status instanceof AttendanceStatus ? $row->status->value : (string) $row->status] = (int) $row->total;
        }

        $trend = [];
        $cursor = $from->copy();
        while ($cursor->lte($to) && count($trend) < 62) {
            $key = $cursor->toDateString();
            $bucket = $byDate[$key] ?? [];
            $counts = [];
            foreach (AttendanceStatus::cases() as $status) {
                $counts[$status->value] = (int) ($bucket[$status->value] ?? 0);
            }
            $trend[] = [
                'date' => $key,
                'counts' => $counts,
                'present' => $counts[AttendanceStatus::Present->value],
                'late' => $counts[AttendanceStatus::Late->value],
                'on_leave' => $counts[AttendanceStatus::OnLeave->value],
                'other' => array_sum($counts) - $counts[AttendanceStatus::Present->value] - $counts[AttendanceStatus::Late->value] - $counts[AttendanceStatus::OnLeave->value],
            ];
            $cursor->addDay();
        }

        return $trend;
    }

    /**
     * Every stored attendance status in the range, including zeroes.
     *
     * @return list<array{key: string, label: string, total: int}>
     */
    public function statusMix(Organization $organization, Carbon $from, Carbon $to): array
    {
        $rows = AttendanceRecord::query()
            ->where('organization_id', $organization->getKey())
            ->where('work_date', '>=', $from->toDateString())
            ->where('work_date', '<', CalendarDay::following($to->toDateString()))
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->get();

        $counts = [];
        foreach ($rows as $row) {
            $status = $row->status instanceof AttendanceStatus ? $row->status->value : (string) $row->status;
            $counts[$status] = (int) $row->total;
        }

        return array_map(fn (AttendanceStatus $status): array => [
            'key' => $status->value,
            'label' => $status->label(),
            'total' => (int) ($counts[$status->value] ?? 0),
        ], AttendanceStatus::cases());
    }

    /**
     * Accepted check-in checks in the range. Reserved methods are omitted.
     * A zero means that method was not used. Wi-Fi can appear here only as support for another method.
     *
     * @return list<array{key: string, label: string, total: int}>
     */
    public function methodMix(Organization $organization, Carbon $from, Carbon $to): array
    {
        $rows = AttendanceEvent::query()
            ->join('attendance_records', 'attendance_records.id', '=', 'attendance_events.attendance_record_id')
            ->where('attendance_events.organization_id', $organization->getKey())
            ->where('attendance_events.type', 'check_in')
            ->where('attendance_events.outcome', 'accepted')
            ->whereNotNull('attendance_events.method')
            ->whereColumn('attendance_records.organization_id', 'attendance_events.organization_id')
            ->whereBetween('attendance_records.work_date', [$from->toDateString(), $to->toDateString()])
            ->select('attendance_events.method', DB::raw('count(*) as total'))
            ->groupBy('attendance_events.method')
            ->get();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(string) $row->method] = (int) $row->total;
        }

        $methods = array_values(array_filter(
            VerificationMethod::cases(),
            fn (VerificationMethod $method): bool => ! $method->isReserved(),
        ));

        return array_map(fn (VerificationMethod $method): array => [
            'key' => $method->value,
            'label' => $method->label(),
            'total' => (int) ($counts[$method->value] ?? 0),
        ], $methods);
    }

    /**
     * Leave requests whose start falls in the range, grouped by status.
     *
     * @return array<string, int>
     */
    public function leaveDecisions(Organization $organization, Carbon $from, Carbon $to): array
    {
        $counts = [];

        foreach (
            LeaveRequest::query()
                ->where('organization_id', $organization->getKey())
                ->where('starts_on', '>=', $from->toDateString())
                ->where('starts_on', '<', CalendarDay::following($to->toDateString()))
                ->select('status', DB::raw('count(*) as total'))
                ->groupBy('status')
                ->pluck('total', 'status') as $status => $total
        ) {
            $key = $status instanceof \BackedEnum ? $status->value : (string) $status;
            $counts[$key] = (int) $total;
        }

        return $counts;
    }

    /**
     * Published assignments in the range, and how many of those person-dates have a present or late record.
     *
     * @return array{scheduled: int, attended: int}
     */
    public function scheduleAdherence(Organization $organization, Carbon $from, Carbon $to): array
    {
        $base = ShiftAssignment::query()
            ->where('organization_id', $organization->getKey())
            ->where('work_date', '>=', $from->toDateString())
            ->where('work_date', '<', CalendarDay::following($to->toDateString()))
            ->whereHas('shift', fn ($query) => $query->where('status', 'published'));

        $scheduled = (clone $base)->count();
        $attended = (clone $base)->whereExists(function ($query): void {
            $query->selectRaw('1')
                ->from('attendance_records')
                ->whereColumn('attendance_records.organization_id', 'shift_assignments.organization_id')
                ->whereColumn('attendance_records.staff_member_id', 'shift_assignments.staff_member_id')
                ->whereColumn('attendance_records.work_date', 'shift_assignments.work_date')
                ->whereIn('attendance_records.status', ['present', 'late']);
        })->count();

        return ['scheduled' => $scheduled, 'attended' => $attended];
    }

    /**
     * @return array<string, int>
     */
    public function arrangementMix(Organization $organization, Carbon $from, Carbon $to): array
    {
        return ShiftAssignment::query()
            ->join('work_arrangements', 'work_arrangements.id', '=', 'shift_assignments.work_arrangement_id')
            ->where('shift_assignments.organization_id', $organization->getKey())
            ->whereColumn('work_arrangements.organization_id', 'shift_assignments.organization_id')
            ->where('shift_assignments.work_date', '>=', $from->toDateString())
            ->where('shift_assignments.work_date', '<', CalendarDay::following($to->toDateString()))
            ->select('work_arrangements.kind', DB::raw('count(*) as total'))
            ->groupBy('work_arrangements.kind')
            ->pluck('total', 'kind')
            ->map(fn ($total) => (int) $total)
            ->all();
    }
}
