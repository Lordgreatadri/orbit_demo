<?php

namespace App\Workforce;

use App\Analytics\AnalyticsScope;
use App\Enums\AttendanceStatus;
use App\Enums\StaffStatus;
use App\Enums\VerificationMethod;
use App\Enums\WorkType;
use App\Models\AttendanceEvent;
use App\Models\AttendanceRecord;
use App\Models\Department;
use App\Models\LeaveRequest;
use App\Models\Organization;
use App\Models\ShiftAssignment;
use App\Models\StaffMember;
use App\Support\CalendarDay;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Tenant attendance figures from stored rows. Aggregates stay in SQL.
 */
final class WorkforceReport
{
    /**
     * Absent is derived from active people with no covering record, unless a
     * location filter is set. A location filter counts the stored absent status
     * at that location, because people without a row there are not the same
     * thing as people who were marked absent.
     *
     * @return array{present: int, late: int, absent: int, on_leave: int, incomplete: int, excused: int, active_staff: int, absent_basis: string}
     */
    public function day(Organization $organization, ?string $date = null, ?AnalyticsScope $scope = null): array
    {
        $date ??= now($organization->timezone ?: 'UTC')->toDateString();

        $counts = $this->constrainAttendance(CalendarDay::constrain(AttendanceRecord::query(), 'work_date', $date), $scope)
            ->select('status', DB::raw('count(distinct staff_member_id) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $active = $this->activePeople($organization, $scope);
        $covered = (int) $this->constrainAttendance(CalendarDay::constrain(AttendanceRecord::query(), 'work_date', $date), $scope)
            ->whereIn('status', [
                AttendanceStatus::Present->value,
                AttendanceStatus::Late->value,
                AttendanceStatus::OnLeave->value,
                AttendanceStatus::Incomplete->value,
                AttendanceStatus::Excused->value,
            ])
            ->distinct()
            ->count('staff_member_id');

        $storedAbsent = (int) ($counts[AttendanceStatus::Absent->value] ?? 0);
        $derived = $scope?->locationId === null;

        return [
            'present' => (int) ($counts[AttendanceStatus::Present->value] ?? 0),
            'late' => (int) ($counts[AttendanceStatus::Late->value] ?? 0),
            'on_leave' => (int) ($counts[AttendanceStatus::OnLeave->value] ?? 0),
            'incomplete' => (int) ($counts[AttendanceStatus::Incomplete->value] ?? 0),
            'excused' => (int) ($counts[AttendanceStatus::Excused->value] ?? 0),
            'absent' => $derived ? max(0, $active - $covered) : $storedAbsent,
            'active_staff' => $active,
            'absent_basis' => $derived ? 'derived' : 'stored',
        ];
    }

    /**
     * @return list<array{date: string, counts: array<string, int>, present: int, late: int, on_leave: int, other: int}>
     */
    public function trend(Organization $organization, Carbon $from, Carbon $to, ?AnalyticsScope $scope = null): array
    {
        $rows = $this->constrainAttendance(AttendanceRecord::query(), $scope)
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
    public function statusMix(Organization $organization, Carbon $from, Carbon $to, ?AnalyticsScope $scope = null): array
    {
        $rows = $this->constrainAttendance(AttendanceRecord::query(), $scope)
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
    public function methodMix(Organization $organization, Carbon $from, Carbon $to, ?AnalyticsScope $scope = null): array
    {
        $query = AttendanceEvent::query()
            ->join('attendance_records', 'attendance_records.id', '=', 'attendance_events.attendance_record_id')
            ->where('attendance_events.organization_id', $organization->getKey());
        $this->constrainAttendance($query, $scope);

        $rows = $query
            ->where('attendance_events.type', 'check_in')
            ->where('attendance_events.outcome', 'accepted')
            ->whereNotNull('attendance_events.method')
            ->whereColumn('attendance_records.organization_id', 'attendance_events.organization_id')
            ->where('attendance_records.work_date', '>=', $from->toDateString())
            ->where('attendance_records.work_date', '<', CalendarDay::following($to->toDateString()))
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
    public function leaveDecisions(Organization $organization, Carbon $from, Carbon $to, ?AnalyticsScope $scope = null): array
    {
        $counts = [];

        foreach (
            $this->constrainPeople(LeaveRequest::query(), $scope, 'leave_requests')
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
    public function scheduleAdherence(Organization $organization, Carbon $from, Carbon $to, ?AnalyticsScope $scope = null): array
    {
        $base = $this->constrainPeople(ShiftAssignment::query(), $scope, 'shift_assignments')
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
                ->whereIn('attendance_records.status', ['present', 'late'])
                ->where(function ($match): void {
                    $match->whereColumn('attendance_records.shift_assignment_id', 'shift_assignments.id')
                        ->orWhere(function ($legacy): void {
                            $legacy->whereNull('attendance_records.shift_assignment_id')
                                ->whereNull('attendance_records.attendance_session_id')
                                ->whereColumn('attendance_records.work_date', 'shift_assignments.work_date')
                                ->whereRaw(
                                    '(select count(*) from shift_assignments as siblings
                                        inner join shifts on shifts.id = siblings.shift_id and shifts.status = ?
                                        where siblings.staff_member_id = shift_assignments.staff_member_id
                                          and siblings.work_date = shift_assignments.work_date
                                          and siblings.organization_id = shift_assignments.organization_id) = 1',
                                    ['published'],
                                );
                        });
                });
        })->count();

        return ['scheduled' => $scheduled, 'attended' => $attended];
    }

    /**
     * @return array<string, int>
     */
    public function arrangementMix(Organization $organization, Carbon $from, Carbon $to, ?AnalyticsScope $scope = null): array
    {
        return $this->constrainPeople(ShiftAssignment::query(), $scope, 'shift_assignments')
            ->join('work_arrangements', 'work_arrangements.id', '=', 'shift_assignments.work_arrangement_id')
            ->where('shift_assignments.organization_id', $organization->getKey())
            ->whereColumn('work_arrangements.organization_id', 'shift_assignments.organization_id')
            ->where('shift_assignments.work_date', '>=', $from->toDateString())
            ->where('shift_assignments.work_date', '<', CalendarDay::following($to->toDateString()))
            ->whereExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('shifts')
                    ->whereColumn('shifts.id', 'shift_assignments.shift_id')
                    ->where('shifts.status', 'published');
            })
            ->select('work_arrangements.kind', DB::raw('count(*) as total'))
            ->groupBy('work_arrangements.kind')
            ->pluck('total', 'kind')
            ->map(fn ($total) => (int) $total)
            ->all();
    }

    /**
     * Attendance rows in the window, grouped by the person's current work type.
     * One record is one row. A person with two periods is counted twice.
     *
     * @return list<array{label: string, value: int}>
     */
    public function workTypeMix(Organization $organization, Carbon $from, Carbon $to, ?AnalyticsScope $scope = null): array
    {
        $counts = $this->constrainAttendance(AttendanceRecord::query(), $scope)
            ->join('staff_members', 'staff_members.id', '=', 'attendance_records.staff_member_id')
            ->where('attendance_records.organization_id', $organization->getKey())
            ->whereColumn('staff_members.organization_id', 'attendance_records.organization_id')
            ->where('attendance_records.work_date', '>=', $from->toDateString())
            ->where('attendance_records.work_date', '<', CalendarDay::following($to->toDateString()))
            ->select('staff_members.work_type', DB::raw('count(*) as total'))
            ->groupBy('staff_members.work_type')
            ->pluck('total', 'work_type');

        return array_map(fn (WorkType $type): array => [
            'label' => $type->label(),
            'value' => (int) ($counts[$type->value] ?? 0),
        ], WorkType::cases());
    }

    /**
     * Record counts by department. A record with no department is omitted.
     * At most eight departments, largest first, unless $limit is null.
     *
     * @return list<array{name: string, present: int, late: int, on_leave: int, other: int, total: int, incomplete: int, excused: int, absent: int}>
     */
    public function departmentMix(Organization $organization, Carbon $from, Carbon $to, ?AnalyticsScope $scope = null, ?int $limit = 8): array
    {
        $rows = $this->constrainAttendance(AttendanceRecord::query(), $scope)
            ->where('organization_id', $organization->getKey())
            ->where('work_date', '>=', $from->toDateString())
            ->where('work_date', '<', CalendarDay::following($to->toDateString()))
            ->whereNotNull('department_id')
            ->select('department_id', 'status', DB::raw('count(*) as total'))
            ->groupBy('department_id', 'status')
            ->get();

        $names = Department::query()
            ->where('organization_id', $organization->getKey())
            ->pluck('name', 'id');

        $mapped = $rows
            ->groupBy('department_id')
            ->map(function ($group, $departmentId) use ($names): array {
                $byStatus = $group->mapWithKeys(fn ($row) => [
                    $row->status instanceof AttendanceStatus ? $row->status->value : (string) $row->status => (int) $row->total,
                ]);

                return [
                    'name' => $names[$departmentId] ?? 'Department',
                    'present' => (int) ($byStatus['present'] ?? 0),
                    'late' => (int) ($byStatus['late'] ?? 0),
                    'on_leave' => (int) ($byStatus['on_leave'] ?? 0),
                    'incomplete' => (int) ($byStatus['incomplete'] ?? 0),
                    'excused' => (int) ($byStatus['excused'] ?? 0),
                    'absent' => (int) ($byStatus['absent'] ?? 0),
                    'other' => (int) $byStatus->except(['present', 'late', 'on_leave'])->sum(),
                    'total' => (int) $group->sum('total'),
                ];
            })
            ->sortByDesc('total')
            ->values();

        if ($limit !== null) {
            $mapped = $mapped->take($limit);
        }

        return $mapped->all();
    }

    /**
     * Attendance aggregates per person in the range. One person is one row.
     *
     * @return list<array{staff_number: string, name: string, department: string, present: int, late: int, on_leave: int, incomplete: int, excused: int, absent: int, late_minutes: int, total: int}>
     */
    public function byPerson(Organization $organization, Carbon $from, Carbon $to, ?AnalyticsScope $scope = null): array
    {
        $rows = $this->constrainAttendance(AttendanceRecord::query(), $scope)
            ->join('staff_members', 'staff_members.id', '=', 'attendance_records.staff_member_id')
            ->leftJoin('departments', 'departments.id', '=', 'attendance_records.department_id')
            ->where('attendance_records.organization_id', $organization->getKey())
            ->whereColumn('staff_members.organization_id', 'attendance_records.organization_id')
            ->where('attendance_records.work_date', '>=', $from->toDateString())
            ->where('attendance_records.work_date', '<', CalendarDay::following($to->toDateString()))
            ->select(
                'attendance_records.staff_member_id',
                'staff_members.staff_number',
                'staff_members.first_name',
                'staff_members.last_name',
                'departments.name as department_name',
                'attendance_records.status',
                DB::raw('count(*) as total'),
                DB::raw('coalesce(sum(attendance_records.late_minutes), 0) as late_minutes'),
            )
            ->groupBy(
                'attendance_records.staff_member_id',
                'staff_members.staff_number',
                'staff_members.first_name',
                'staff_members.last_name',
                'departments.name',
                'attendance_records.status',
            )
            ->get();

        return $rows
            ->groupBy('staff_member_id')
            ->map(function ($group): array {
                $first = $group->first();
                $byStatus = $group->mapWithKeys(fn ($row) => [
                    $row->status instanceof AttendanceStatus ? $row->status->value : (string) $row->status => (int) $row->total,
                ]);

                return [
                    'staff_number' => (string) ($first->staff_number ?? ''),
                    'name' => trim(($first->first_name ?? '').' '.($first->last_name ?? '')),
                    'department' => (string) ($first->department_name ?? ''),
                    'present' => (int) ($byStatus['present'] ?? 0),
                    'late' => (int) ($byStatus['late'] ?? 0),
                    'on_leave' => (int) ($byStatus['on_leave'] ?? 0),
                    'incomplete' => (int) ($byStatus['incomplete'] ?? 0),
                    'excused' => (int) ($byStatus['excused'] ?? 0),
                    'absent' => (int) ($byStatus['absent'] ?? 0),
                    'late_minutes' => (int) $group->sum('late_minutes'),
                    'total' => (int) $group->sum('total'),
                ];
            })
            ->sortBy('name')
            ->values()
            ->all();
    }

    /**
     * Late arrivals in the range: people and total late minutes.
     *
     * @return array{people: int, records: int, late_minutes: int}
     */
    public function punctuality(Organization $organization, Carbon $from, Carbon $to, ?AnalyticsScope $scope = null): array
    {
        $query = $this->constrainAttendance(AttendanceRecord::query(), $scope)
            ->where('organization_id', $organization->getKey())
            ->where('work_date', '>=', $from->toDateString())
            ->where('work_date', '<', CalendarDay::following($to->toDateString()))
            ->where('status', AttendanceStatus::Late->value);

        return [
            'people' => (int) (clone $query)->distinct()->count('staff_member_id'),
            'records' => (int) (clone $query)->count(),
            'late_minutes' => (int) (clone $query)->sum('late_minutes'),
        ];
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    private function constrainAttendance(Builder $query, ?AnalyticsScope $scope): Builder
    {
        if ($scope === null) {
            return $query;
        }

        $query->where('attendance_records.organization_id', $scope->organization->getKey());

        if ($scope->departmentIds !== null) {
            $query->whereIn('attendance_records.department_id', $scope->departmentIds === [] ? [-1] : $scope->departmentIds);
        }

        if ($scope->staffMemberId !== null) {
            $query->where('attendance_records.staff_member_id', $scope->staffMemberId);
        }

        if ($scope->locationId !== null) {
            $query->where('attendance_records.location_id', $scope->locationId);
        }

        return $query;
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    private function constrainPeople(Builder $query, ?AnalyticsScope $scope, string $table): Builder
    {
        if ($scope === null) {
            return $query;
        }

        $query->where($table.'.organization_id', $scope->organization->getKey());

        if ($scope->staffMemberId !== null) {
            $query->where($table.'.staff_member_id', $scope->staffMemberId);
        }

        if ($scope->departmentIds !== null) {
            $ids = $scope->departmentIds === [] ? [-1] : $scope->departmentIds;
            $query->whereIn($table.'.staff_member_id', StaffMember::query()
                ->where('organization_id', $scope->organization->getKey())
                ->whereIn('department_id', $ids)
                ->select('id'));
        }

        if ($scope->locationId !== null && $table === 'shift_assignments') {
            $query->where($table.'.location_id', $scope->locationId);
        }

        return $query;
    }

    private function activePeople(Organization $organization, ?AnalyticsScope $scope): int
    {
        $query = StaffMember::query()
            ->where('organization_id', $organization->getKey())
            ->where('status', StaffStatus::Active->value);

        if ($scope === null) {
            return $query->count();
        }

        if ($scope->departmentIds !== null) {
            $query->whereIn('department_id', $scope->departmentIds === [] ? [-1] : $scope->departmentIds);
        }

        if ($scope->staffMemberId !== null) {
            $query->whereKey($scope->staffMemberId);
        }

        if ($scope->locationId !== null) {
            $query->where('primary_location_id', $scope->locationId);
        }

        return $query->count();
    }
}
