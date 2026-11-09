<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\FieldActivity;
use App\Models\LeaveRequest;
use App\Models\Organization;
use Carbon\CarbonInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;

class WorkforceOverviewController extends Controller
{
    public function __invoke(): View
    {
        $organizations = Organization::query()->orderBy('name')->paginate(20);
        $ids = $organizations->getCollection()->pluck('id');
        $dates = $organizations->getCollection()->mapWithKeys(
            fn (Organization $organization) => [$organization->getKey() => now($organization->timezone ?: 'UTC')->toDateString()],
        );

        $counts = AttendanceRecord::withoutTenantScope()
            ->whereIn('organization_id', $ids)
            ->whereIn('work_date', $dates->unique()->values())
            ->select('organization_id', 'work_date', 'status', DB::raw('count(*) as total'))
            ->groupBy('organization_id', 'work_date', 'status')
            ->get()
            ->filter(function ($row) use ($dates) {
                $date = $row->work_date instanceof CarbonInterface
                    ? $row->work_date->toDateString()
                    : (string) $row->work_date;

                return ($dates[$row->organization_id] ?? null) === $date;
            })
            ->groupBy('organization_id');

        $openField = FieldActivity::withoutTenantScope()
            ->whereIn('organization_id', $ids)
            ->whereNull('ended_at')
            ->select('organization_id', DB::raw('count(*) as total'))
            ->groupBy('organization_id')
            ->pluck('total', 'organization_id');

        $pendingLeave = LeaveRequest::withoutTenantScope()
            ->whereIn('organization_id', $ids)
            ->where('status', 'submitted')
            ->select('organization_id', DB::raw('count(*) as total'))
            ->groupBy('organization_id')
            ->pluck('total', 'organization_id');

        return view('platform.workforce.overview', [
            'organizations' => $organizations,
            'counts' => $counts,
            'openField' => $openField,
            'pendingLeave' => $pendingLeave,
        ]);
    }
}
