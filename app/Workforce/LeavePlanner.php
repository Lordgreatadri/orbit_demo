<?php

namespace App\Workforce;

use App\Enums\LeaveApprovalMode;
use App\Enums\LeaveCountMode;
use App\Enums\WorkArrangementKind;
use App\Models\LeaveEntitlementRule;
use App\Models\LeavePolicy;
use App\Models\LeaveType;
use App\Models\NonWorkingDay;
use App\Models\Organization;
use App\Models\StaffMember;
use App\Models\WorkArrangement;
use App\Support\CalendarDay;
use Illuminate\Support\Carbon;

/**
 * Tenant leave-year, day counting, and entitlement matching.
 * Numbers come from the tenant's rules. ORBIT does not ship band values.
 */
final class LeavePlanner
{
    public function ensurePolicy(LeaveType $type): LeavePolicy
    {
        return LeavePolicy::query()->firstOrCreate(
            [
                'organization_id' => $type->organization_id,
                'leave_type_id' => $type->getKey(),
            ],
            [
                'name' => $type->name.' policy',
                'year_start_month' => 1,
                'year_start_day' => 1,
                'count_mode' => LeaveCountMode::Calendar,
                'weekend_days' => [6, 7],
                'exclude_non_working' => true,
                'approval_mode' => LeaveApprovalMode::AnyApprover,
                'allow_self_approval' => false,
                'effective_from' => now()->startOfYear()->toDateString(),
                'is_active' => true,
            ],
        );
    }

    public function policyOn(LeaveType $type, string $on): ?LeavePolicy
    {
        return LeavePolicy::query()
            ->where('leave_type_id', $type->getKey())
            ->where('is_active', true)
            ->where('effective_from', '<', CalendarDay::following($on))
            ->where(function ($query) use ($on): void {
                $query->whereNull('effective_until')->orWhere('effective_until', '>=', $on);
            })
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return array{0: string, 1: string}
     */
    public function period(LeavePolicy $policy, string $on): array
    {
        $date = Carbon::parse($on)->startOfDay();
        $start = $this->anchor($date->year, (int) $policy->year_start_month, (int) $policy->year_start_day);

        if ($date->lt($start)) {
            $start = $this->anchor($date->year - 1, (int) $policy->year_start_month, (int) $policy->year_start_day);
        }

        $next = $this->anchor($start->year + 1, (int) $policy->year_start_month, (int) $policy->year_start_day);

        return [$start->toDateString(), $next->copy()->subDay()->toDateString()];
    }

    /**
     * Dates that consume entitlement. Working mode skips the tenant's weekend and non-working calendar.
     *
     * @return list<string>
     */
    public function coveredDates(?LeavePolicy $policy, int $organizationId, string $starts, string $ends): array
    {
        $working = $policy?->count_mode === LeaveCountMode::Working;
        $weekends = array_map('intval', $policy?->weekend_days ?? []);
        $closed = [];

        if ($working && ($policy === null || $policy->exclude_non_working)) {
            $closed = NonWorkingDay::query()
                ->where('organization_id', $organizationId)
                ->where('observed_on', '>=', $starts)
                ->where('observed_on', '<', CalendarDay::following($ends))
                ->pluck('observed_on')
                ->map(fn ($day) => Carbon::parse($day)->toDateString())
                ->all();
        }

        $dates = [];
        $cursor = Carbon::parse($starts)->startOfDay();
        $end = Carbon::parse($ends)->startOfDay();

        while ($cursor->lte($end) && count($dates) < 366) {
            $iso = (int) $cursor->dayOfWeekIso;
            $key = $cursor->toDateString();
            $skip = $working && (in_array($iso, $weekends, true) || in_array($key, $closed, true));

            if (! $skip) {
                $dates[] = $key;
            }

            $cursor->addDay();
        }

        return $dates;
    }

    public function entitledDays(StaffMember $staff, LeaveType $type, ?LeavePolicy $policy, string $periodStart): int
    {
        if ($policy !== null) {
            $rule = $this->matchingRule($staff, $policy, $periodStart);

            if ($rule !== null) {
                return (int) $rule->entitled_days;
            }
        }

        return (int) ($type->default_days ?? 0);
    }

    public function matchingRule(StaffMember $staff, LeavePolicy $policy, string $periodStart): ?LeaveEntitlementRule
    {
        $rules = LeaveEntitlementRule::query()
            ->where('leave_policy_id', $policy->getKey())
            ->where('effective_from', '<', CalendarDay::following($periodStart))
            ->where(function ($query) use ($periodStart): void {
                $query->whereNull('effective_until')->orWhere('effective_until', '>=', $periodStart);
            })
            ->orderBy('priority')
            ->orderBy('id')
            ->get();

        $months = null;

        if ($staff->started_on !== null) {
            $months = (int) $staff->started_on->diffInMonths(Carbon::parse($periodStart), false);
            $months = max(0, $months);
        }

        foreach ($rules as $rule) {
            if ($rule->staff_category_id !== null && (int) $rule->staff_category_id !== (int) $staff->staff_category_id) {
                continue;
            }

            if ($rule->department_id !== null && (int) $rule->department_id !== (int) $staff->department_id) {
                continue;
            }

            if (filled($rule->grade) && strcasecmp((string) $rule->grade, (string) $staff->grade) !== 0) {
                continue;
            }

            if (filled($rule->employment_type) && strcasecmp((string) $rule->employment_type, (string) $staff->employment_type) !== 0) {
                continue;
            }

            if ($rule->min_service_months !== null && ($months === null || $months < (int) $rule->min_service_months)) {
                continue;
            }

            if ($rule->max_service_months !== null && ($months === null || $months > (int) $rule->max_service_months)) {
                continue;
            }

            return $rule;
        }

        return null;
    }

    public function ensureArrangements(Organization $organization): void
    {
        $defaults = [
            ['office', 'Office', WorkArrangementKind::Office],
            ['remote', 'Remote', WorkArrangementKind::Remote],
            ['field', 'Field', WorkArrangementKind::Field],
            ['flexible', 'Flexible', WorkArrangementKind::Flexible],
        ];

        foreach ($defaults as [$code, $name, $kind]) {
            WorkArrangement::query()->firstOrCreate(
                ['organization_id' => $organization->getKey(), 'code' => $code],
                ['name' => $name, 'kind' => $kind, 'is_active' => true],
            );
        }
    }

    private function anchor(int $year, int $month, int $day): Carbon
    {
        $month = min(12, max(1, $month));
        $probe = Carbon::create($year, $month, 1);
        $day = min($probe->daysInMonth, max(1, $day));

        return Carbon::create($year, $month, $day)->startOfDay();
    }
}
