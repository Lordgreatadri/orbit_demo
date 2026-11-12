<?php

namespace App\Http\Controllers\Platform;

use App\Enums\AttendanceStatus;
use App\Enums\StaffStatus;
use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\FieldActivity;
use App\Models\LeaveRequest;
use App\Models\Organization;
use App\Models\StaffMember;
use App\Support\CalendarDay;
use Carbon\CarbonInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Per-organisation workforce counts for today in each organisation's own time
 * zone. Aggregate SQL only. Member names and individual records are never read.
 */
class WorkforceOverviewController extends Controller
{
    public function __invoke(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));

        $organizations = Organization::query()
            ->when($search !== '', fn ($query) => $query->whereRaw('lower(name) like ?', ['%'.mb_strtolower($search).'%']))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        // Each organisation's "today" depends on its time zone. There are only a
        // handful of distinct dates worldwide at any moment, so one grouped query
        // covers every organisation and is filtered to the right date per row.
        $todayById = Organization::query()
            ->pluck('timezone', 'id')
            ->map(fn (?string $timezone) => now($timezone ?: 'UTC')->toDateString());

        $attendance = $this->attendanceByOrganization($todayById);
        $coverage = $this->activeCoverage($todayById);
        $activeStaff = StaffMember::withoutTenantScope()
            ->where('status', StaffStatus::Active->value)
            ->select('organization_id', DB::raw('count(*) as total'))
            ->groupBy('organization_id')
            ->pluck('total', 'organization_id');
        $openField = FieldActivity::withoutTenantScope()
            ->whereNull('ended_at')
            ->select('organization_id', DB::raw('count(*) as total'))
            ->groupBy('organization_id')
            ->pluck('total', 'organization_id');
        $pendingLeave = LeaveRequest::withoutTenantScope()
            ->where('status', 'submitted')
            ->select('organization_id', DB::raw('count(*) as total'))
            ->groupBy('organization_id')
            ->pluck('total', 'organization_id');

        $statusTotal = fn (string $status): int => (int) $attendance->sum(fn (array $row) => $row[$status] ?? 0);
        $covering = ['present', 'late', 'on_leave', 'incomplete', 'excused'];

        $totals = [
            'organizations' => $todayById->count(),
            'reporting' => $attendance->filter(fn (array $row) => array_sum(array_intersect_key($row, array_flip($covering))) > 0)->count(),
            'active_staff' => (int) $activeStaff->sum(),
            'present' => $statusTotal('present'),
            'late' => $statusTotal('late'),
            'on_leave' => $statusTotal('on_leave'),
            'covered' => (int) $coverage->sum('covered'),
            'open_field' => (int) $openField->sum(),
            'pending_leave' => (int) $pendingLeave->sum(),
        ];

        return view('platform.workforce.overview', [
            'organizations' => $organizations,
            'attendance' => $attendance,
            'coverageBuckets' => $coverage,
            'activeStaff' => $activeStaff,
            'openField' => $openField,
            'pendingLeave' => $pendingLeave,
            'todayById' => $todayById,
            'totals' => $totals,
            'search' => $search,
        ]);
    }

    /**
     * @param  Collection<int, string>  $todayById
     * @return Collection<int, array<string, int>>
     */
    private function attendanceByOrganization(Collection $todayById): Collection
    {
        if ($todayById->isEmpty()) {
            return collect();
        }

        $query = AttendanceRecord::withoutTenantScope();
        CalendarDay::constrainAny($query, 'work_date', $todayById->unique()->values()->all());

        return $query
            ->select('organization_id', 'work_date', 'status', DB::raw('count(distinct staff_member_id) as total'))
            ->groupBy('organization_id', 'work_date', 'status')
            ->get()
            ->filter(function ($row) use ($todayById): bool {
                $date = $row->work_date instanceof CarbonInterface
                    ? $row->work_date->toDateString()
                    : substr((string) $row->work_date, 0, 10);

                return ($todayById[$row->organization_id] ?? null) === $date;
            })
            ->groupBy('organization_id')
            ->map(fn (Collection $rows): array => $rows->mapWithKeys(fn ($row) => [
                ($row->status->value ?? (string) $row->status) => (int) $row->total,
            ])->all());
    }

    /**
     * Distinct active people with any covering status today. A person recorded
     * as present in one period and late in another counts once. Bar segments
     * use the same people in priority order: present, then late, then the
     * other covering statuses, so the segments do not overlap.
     *
     * @param  Collection<int, string>  $todayById
     * @return Collection<int, array{present: int, late: int, other: int, covered: int}>
     */
    private function activeCoverage(Collection $todayById): Collection
    {
        if ($todayById->isEmpty()) {
            return collect();
        }

        $people = AttendanceRecord::withoutTenantScope()
            ->join('staff_members', function ($join): void {
                $join->on('staff_members.id', '=', 'attendance_records.staff_member_id')
                    ->where('staff_members.status', StaffStatus::Active->value)
                    ->whereNull('staff_members.deleted_at')
                    ->whereColumn('staff_members.organization_id', 'attendance_records.organization_id');
            })
            ->whereIn('attendance_records.status', [
                AttendanceStatus::Present->value,
                AttendanceStatus::Late->value,
                AttendanceStatus::OnLeave->value,
                AttendanceStatus::Incomplete->value,
                AttendanceStatus::Excused->value,
            ]);
        CalendarDay::constrainAny($people, 'attendance_records.work_date', $todayById->unique()->values()->all());

        $summary = DB::query()
            ->fromSub(
                $people
                    ->select('attendance_records.organization_id', 'attendance_records.work_date', 'attendance_records.staff_member_id')
                    ->selectRaw('max(case when attendance_records.status = ? then 1 else 0 end) as has_present', [AttendanceStatus::Present->value])
                    ->selectRaw('max(case when attendance_records.status = ? then 1 else 0 end) as has_late', [AttendanceStatus::Late->value])
                    ->selectRaw('max(case when attendance_records.status in (?, ?, ?) then 1 else 0 end) as has_other', [
                        AttendanceStatus::OnLeave->value,
                        AttendanceStatus::Incomplete->value,
                        AttendanceStatus::Excused->value,
                    ])
                    ->groupBy('attendance_records.organization_id', 'attendance_records.work_date', 'attendance_records.staff_member_id'),
                'covering_people',
            )
            ->select('organization_id', 'work_date')
            ->selectRaw('sum(case when has_present = 1 then 1 else 0 end) as present_people')
            ->selectRaw('sum(case when has_present = 0 and has_late = 1 then 1 else 0 end) as late_people')
            ->selectRaw('sum(case when has_present = 0 and has_late = 0 and has_other = 1 then 1 else 0 end) as other_people')
            ->groupBy('organization_id', 'work_date')
            ->get();

        return $summary
            ->filter(function ($row) use ($todayById): bool {
                $date = $row->work_date instanceof CarbonInterface
                    ? $row->work_date->toDateString()
                    : substr((string) $row->work_date, 0, 10);

                return ($todayById[$row->organization_id] ?? null) === $date;
            })
            ->groupBy('organization_id')
            ->map(function (Collection $rows): array {
                $present = (int) $rows->sum('present_people');
                $late = (int) $rows->sum('late_people');
                $other = (int) $rows->sum('other_people');

                return [
                    'present' => $present,
                    'late' => $late,
                    'other' => $other,
                    'covered' => $present + $late + $other,
                ];
            });
    }
}
