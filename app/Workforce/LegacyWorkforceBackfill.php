<?php

namespace App\Workforce;

use App\Models\AttendanceRecord;
use App\Models\LeaveAdjustment;
use App\Models\LeaveBalance;
use App\Models\LeavePolicy;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Organization;
use App\Models\ShiftAssignment;
use App\Support\Audit;
use App\Support\CalendarDay;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One-time, repeatable repair for rows created before schedule and leave
 * policy. Running it twice sets the same reservations and links.
 */
final class LegacyWorkforceBackfill
{
    /**
     * The stored entitlement is left as it was. This row is only the excess
     * that submitted and used days had already committed.
     */
    public const LEGACY_EXCESS_REASON = 'Legacy submitted and used days already exceeded the stored entitlement. The entitlement was not changed. This adjustment records that excess so those days stay on record.';

    public static function leaveAccounts(): void
    {
        if (! Schema::hasColumn('leave_requests', 'leave_policy_id') || ! Schema::hasColumn('leave_balances', 'pending_days')) {
            return;
        }

        LeaveRequest::withoutTenantScope()
            ->whereNull('leave_policy_id')
            ->orderBy('id')
            ->each(function (LeaveRequest $request): void {
                $policy = self::calendarPolicy((int) $request->organization_id, (int) $request->leave_type_id);

                if ($policy === null) {
                    return;
                }

                $request->forceFill(['leave_policy_id' => $policy->getKey()])->save();
            });

        $touched = [];

        LeaveRequest::withoutTenantScope()
            ->where('status', 'submitted')
            ->with('policy')
            ->orderBy('id')
            ->each(function (LeaveRequest $request) use (&$touched): void {
                $policy = $request->policy;
                $on = $request->starts_on->toDateString();
                [$start, $end] = $policy === null
                    ? [substr($on, 0, 4).'-01-01', substr($on, 0, 4).'-12-31']
                    : app(LeavePlanner::class)->period($policy, $on);
                $key = $request->staff_member_id.'|'.$request->leave_type_id.'|'.$start;
                $touched[$key] = [$request->organization_id, $request->staff_member_id, $request->leave_type_id, $start, $end];
            });

        foreach ($touched as [$organizationId, $staffId, $typeId, $start, $end]) {
            $balance = CalendarDay::constrain(
                LeaveBalance::withoutTenantScope()
                    ->where('staff_member_id', $staffId)
                    ->where('leave_type_id', $typeId),
                'period_start',
                $start,
            )->first();

            if ($balance === null) {
                try {
                    $balance = LeaveBalance::withoutTenantScope()->create([
                        'organization_id' => $organizationId,
                        'staff_member_id' => $staffId,
                        'leave_type_id' => $typeId,
                        'year' => (int) substr($start, 0, 4),
                        'period_start' => $start,
                        'period_end' => $end,
                        'entitled_days' => (int) LeaveType::withoutTenantScope()->whereKey($typeId)->value('default_days'),
                        'used_days' => 0,
                        'pending_days' => 0,
                        'adjusted_days' => 0,
                    ]);
                } catch (UniqueConstraintViolationException) {
                    $balance = LeaveBalance::withoutTenantScope()
                        ->where('staff_member_id', $staffId)
                        ->where('leave_type_id', $typeId)
                        ->where('year', (int) substr($start, 0, 4))
                        ->first();

                    if ($balance === null || $balance->period_start?->toDateString() !== $start) {
                        continue;
                    }
                }
            }

            $pending = (int) LeaveRequest::withoutTenantScope()
                ->where('staff_member_id', $staffId)
                ->where('leave_type_id', $typeId)
                ->where('status', 'submitted')
                ->where('starts_on', '>=', $start)
                ->where('starts_on', '<', CalendarDay::following($end))
                ->sum('day_count');

            DB::transaction(function () use ($balance, $pending): void {
                $locked = LeaveBalance::withoutTenantScope()->whereKey($balance->getKey())->lockForUpdate()->first();

                if ($locked === null) {
                    return;
                }

                $used = (int) $locked->used_days;
                $adjusted = (int) $locked->adjusted_days;
                $entitled = (int) $locked->entitled_days;
                $shortfall = $pending + $used - $entitled - $adjusted;

                if ($shortfall > 0) {
                    $adjustment = LeaveAdjustment::withoutTenantScope()->create([
                        'organization_id' => $locked->organization_id,
                        'leave_balance_id' => $locked->getKey(),
                        'staff_member_id' => $locked->staff_member_id,
                        'days' => $shortfall,
                        'reason' => self::LEGACY_EXCESS_REASON,
                        'adjusted_by' => null,
                    ]);
                    $adjusted += $shortfall;
                    Audit::log('leave.adjusted', $adjustment, [
                        'days' => $shortfall,
                        'entitled_days' => $entitled,
                        'legacy_reconciliation' => true,
                    ], null, (int) $locked->organization_id);
                }

                $locked->forceFill([
                    'pending_days' => $pending,
                    'adjusted_days' => $adjusted,
                ])->save();
            });
        }
    }

    public static function attendanceLinks(): void
    {
        if (! Schema::hasColumn('attendance_records', 'shift_assignment_id')) {
            return;
        }

        $resolver = app(ScheduleResolver::class);

        AttendanceRecord::withoutTenantScope()
            ->whereNull('shift_assignment_id')
            ->whereNull('attendance_session_id')
            ->whereNotNull('check_in_at')
            ->orderBy('id')
            ->each(function (AttendanceRecord $record) use ($resolver): void {
                $date = $record->work_date->toDateString();
                $assignments = CalendarDay::constrain(
                    ShiftAssignment::withoutTenantScope()
                        ->where('staff_member_id', $record->staff_member_id),
                    'work_date',
                    $date,
                )
                    ->whereHas('shift', fn ($query) => $query->where('status', 'published'))
                    ->with('shift')
                    ->get();

                if ($assignments->isEmpty()) {
                    return;
                }

                $match = $assignments->count() === 1
                    ? $assignments->first()
                    : self::onlyContaining($resolver, $record, $assignments, $date);

                if ($match === null) {
                    return;
                }

                $periodKey = AttendanceRecord::shiftPeriod((int) $match->getKey());
                $taken = AttendanceRecord::withoutTenantScope()
                    ->where('staff_member_id', $record->staff_member_id)
                    ->where('period_key', $periodKey)
                    ->whereKeyNot($record->getKey())
                    ->exists();

                if ($taken) {
                    return;
                }

                $record->forceFill([
                    'shift_assignment_id' => $match->getKey(),
                    'period_key' => $periodKey,
                ])->save();
            });
    }

    private static function calendarPolicy(int $organizationId, int $typeId): ?LeavePolicy
    {
        $existing = LeavePolicy::withoutTenantScope()
            ->where('organization_id', $organizationId)
            ->where('leave_type_id', $typeId)
            ->where('year_start_month', 1)
            ->where('year_start_day', 1)
            ->orderBy('id')
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $type = LeaveType::withoutTenantScope()->find($typeId);

        if ($type === null) {
            return null;
        }

        return LeavePolicy::withoutTenantScope()->create([
            'organization_id' => $organizationId,
            'leave_type_id' => $typeId,
            'name' => $type->name.' calendar year',
            'year_start_month' => 1,
            'year_start_day' => 1,
            'count_mode' => 'calendar',
            'weekend_days' => [6, 7],
            'exclude_non_working' => true,
            'approval_mode' => 'any_approver',
            'allow_self_approval' => false,
            'effective_from' => '2000-01-01',
            'is_active' => true,
        ]);
    }

    /**
     * @param  Collection<int, ShiftAssignment>  $assignments
     */
    private static function onlyContaining(ScheduleResolver $resolver, AttendanceRecord $record, $assignments, string $date): ?ShiftAssignment
    {
        $timezone = Organization::query()->whereKey($record->organization_id)->value('timezone') ?: 'UTC';
        $at = $record->check_in_at->copy()->timezone($timezone);
        $matches = $assignments->filter(function (ShiftAssignment $assignment) use ($resolver, $date, $timezone, $at): bool {
            if ($assignment->shift === null) {
                return false;
            }

            [$start, $end] = $resolver->bounds($date, $assignment->shift, $timezone, $assignment);

            return $at->greaterThanOrEqualTo($start) && $at->lessThan($end);
        });

        return $matches->count() === 1 ? $matches->first() : null;
    }
}
