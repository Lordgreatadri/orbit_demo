<?php

namespace App\Workforce;

use App\Enums\LeaveApprovalMode;
use App\Enums\LeaveStatus;
use App\Models\LeaveAdjustment;
use App\Models\LeaveBalance;
use App\Models\LeavePolicy;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\StaffMember;
use App\Models\User;
use App\Support\Audit;
use App\Support\CalendarDay;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

final class LeaveService
{
    public function __construct(
        private readonly LeavePlanner $planner,
        private readonly AttendanceEngine $attendance,
    ) {}

    public function submit(User $actor, StaffMember $staff, LeaveType $type, string $starts, string $ends, string $reason, ?UploadedFile $document): LeaveRequest
    {
        if ($ends < $starts) {
            throw new AttendanceException('invalid_dates', 'The leave end date must be on or after the start date.');
        }

        $policy = $this->planner->policyOn($type, $starts) ?? $this->planner->ensurePolicy($type);
        $dates = $this->planner->coveredDates($policy, (int) $staff->organization_id, $starts, $ends);

        if ($dates === []) {
            throw new AttendanceException('invalid_dates', 'Those dates do not include a day this leave policy counts.');
        }

        $this->assertSinglePeriod($policy, $starts, $ends);

        $path = $document?->store('leave/'.$staff->organization_id);

        $request = DB::transaction(function () use ($actor, $staff, $type, $policy, $starts, $ends, $reason, $path, $dates): LeaveRequest {
            $this->lockPerson($staff);
            $this->assertNoOverlap($staff, $starts, $ends);

            if ($type->requires_balance) {
                $balance = $this->lockedBalance($staff, $type, $policy, $starts);

                if ($balance->available() < count($dates)) {
                    throw new AttendanceException('insufficient_balance', 'The remaining leave balance does not cover these dates.');
                }

                $balance->increment('pending_days', count($dates));
            }

            $request = LeaveRequest::query()->create([
                'organization_id' => $staff->organization_id,
                'staff_member_id' => $staff->getKey(),
                'leave_type_id' => $type->getKey(),
                'leave_policy_id' => $policy->getKey(),
                'starts_on' => $starts,
                'ends_on' => $ends,
                'day_count' => count($dates),
                'covered_dates' => $dates,
                'reason' => $reason,
                'status' => LeaveStatus::Submitted,
                'document_path' => $path,
            ]);

            Audit::log('leave.submitted', $request, ['days' => count($dates)], $actor);
            DB::afterCommit(fn () => app(WorkforceNotifier::class)->leaveSubmitted($request));

            return $request;
        });

        return $request;
    }

    public function amend(User $actor, LeaveRequest $request, string $starts, string $ends, string $reason): void
    {
        DB::transaction(function () use ($actor, $request, $starts, $ends, $reason): void {
            [$staff, $locked] = $this->begin($request);

            if ((int) $actor->staffMember?->getKey() !== (int) $staff->getKey()) {
                throw new AttendanceException('not_owner', 'Only the person who submitted this request can change it.');
            }

            if ($locked->status !== LeaveStatus::Submitted) {
                throw new AttendanceException('already_decided', 'Only a request that is still waiting can be changed.');
            }

            if ($ends < $starts) {
                throw new AttendanceException('invalid_dates', 'The leave end date must be on or after the start date.');
            }

            $locked->loadMissing('leaveType', 'policy');
            $policy = $locked->policy;
            $dates = $this->planner->coveredDates($policy, (int) $staff->organization_id, $starts, $ends);

            if ($dates === []) {
                throw new AttendanceException('invalid_dates', 'Those dates do not include a day this leave policy counts.');
            }

            $this->assertSinglePeriod($policy, $starts, $ends);
            $this->assertNoOverlap($staff, $starts, $ends, $locked->getKey());
            $this->retargetPending($staff, $locked, $dates, $policy, $starts);

            $locked->update([
                'starts_on' => $starts,
                'ends_on' => $ends,
                'day_count' => count($dates),
                'covered_dates' => $dates,
                'reason' => $reason,
                'leave_policy_id' => $policy?->getKey() ?? $locked->leave_policy_id,
            ]);

            Audit::log('leave.amended', $locked, ['days' => count($dates)], $actor);
        });
    }

    public function withdraw(User $actor, LeaveRequest $request): void
    {
        DB::transaction(function () use ($actor, $request): void {
            [$staff, $locked] = $this->begin($request);

            if ((int) $actor->staffMember?->getKey() !== (int) $staff->getKey()) {
                throw new AttendanceException('not_owner', 'Only the person who submitted this request can withdraw it.');
            }

            if ($locked->status !== LeaveStatus::Submitted) {
                throw new AttendanceException('already_decided', 'Only a request that is still waiting can be withdrawn.');
            }

            $this->releasePending($staff, $locked);
            $locked->update(['status' => LeaveStatus::Withdrawn]);
            Audit::log('leave.withdrawn', $locked, ['days' => $locked->dayCount()], $actor);
            DB::afterCommit(fn () => app(WorkforceNotifier::class)->leaveDecided($locked));
        });
    }

    public function decide(User $actor, LeaveRequest $request, bool $approve, ?string $note): void
    {
        DB::transaction(function () use ($actor, $request, $approve, $note): void {
            [$staff, $locked] = $this->begin($request);

            if ($locked->status !== LeaveStatus::Submitted) {
                throw new AttendanceException('already_decided', 'This request has already been decided.');
            }

            $locked->load('leaveType', 'policy');
            $this->assertCanDecide($actor, $staff, $locked->policy);

            if ($approve) {
                $this->assertNoOverlap($staff, $locked->starts_on->toDateString(), $locked->ends_on->toDateString(), $locked->getKey(), true);
                $this->consumePending($staff, $locked);
                $this->attendance->projectApprovedLeave($staff, $locked->coveredDates());
            } else {
                $this->releasePending($staff, $locked);
            }

            $locked->update([
                'status' => $approve ? LeaveStatus::Approved : LeaveStatus::Rejected,
                'decided_by' => $actor->getKey(),
                'decision_note' => $note,
                'decided_at' => now(),
            ]);

            Audit::log($approve ? 'leave.approved' : 'leave.rejected', $locked, [
                'days' => $locked->dayCount(),
            ], $actor);
            DB::afterCommit(fn () => app(WorkforceNotifier::class)->leaveDecided($locked));
        });
    }

    public function cancel(User $actor, LeaveRequest $request, ?string $note): void
    {
        DB::transaction(function () use ($actor, $request, $note): void {
            [$staff, $locked] = $this->begin($request);

            if ($locked->status !== LeaveStatus::Approved) {
                throw new AttendanceException('already_decided', 'Only approved leave can be cancelled.');
            }

            $locked->load('policy');
            $this->assertCanDecide($actor, $staff, $locked->policy);

            if ($locked->leaveType()->value('requires_balance')) {
                $on = $locked->starts_on->toDateString();
                $policy = $locked->policy;
                $start = $policy === null
                    ? substr($on, 0, 4).'-01-01'
                    : $this->planner->period($policy, $on)[0];
                $balance = CalendarDay::constrain(
                    LeaveBalance::query()
                        ->where('staff_member_id', $staff->getKey())
                        ->where('leave_type_id', $locked->leave_type_id),
                    'period_start',
                    $start,
                )->lockForUpdate()->first();

                if ($balance !== null) {
                    $restore = min($locked->dayCount(), (int) $balance->used_days);
                    if ($restore > 0) {
                        $balance->decrement('used_days', $restore);
                    }
                }
            }

            $this->attendance->clearLeaveProjection($staff, $locked->coveredDates());
            $locked->update([
                'status' => LeaveStatus::Cancelled,
                'decided_by' => $actor->getKey(),
                'decision_note' => $note,
                'decided_at' => now(),
            ]);

            Audit::log('leave.cancelled', $locked, ['days' => $locked->dayCount()], $actor);
            DB::afterCommit(fn () => app(WorkforceNotifier::class)->leaveDecided($locked));
        });
    }

    public function adjust(User $actor, StaffMember $staff, LeaveType $type, int $days, string $reason): LeaveAdjustment
    {
        return DB::transaction(function () use ($actor, $staff, $type, $days, $reason): LeaveAdjustment {
            $this->lockPerson($staff);
            $policy = $this->planner->policyOn($type, now()->toDateString()) ?? $this->planner->ensurePolicy($type);
            $balance = $this->lockedBalance($staff, $type, $policy, now()->toDateString());
            $nextAdjusted = (int) $balance->adjusted_days + $days;
            $committed = (int) $balance->pending_days + (int) $balance->used_days;

            if ($committed > (int) $balance->entitled_days + $nextAdjusted) {
                throw new AttendanceException('insufficient_balance', 'That adjustment would take the balance below the days already reserved or used.');
            }

            $balance->increment('adjusted_days', $days);

            $adjustment = LeaveAdjustment::query()->create([
                'organization_id' => $staff->organization_id,
                'leave_balance_id' => $balance->getKey(),
                'staff_member_id' => $staff->getKey(),
                'days' => $days,
                'reason' => $reason,
                'adjusted_by' => $actor->getKey(),
            ]);

            Audit::log('leave.adjusted', $adjustment, ['days' => $days, 'leave_type' => $type->code], $actor);

            return $adjustment;
        });
    }

    /**
     * @return array{entitled: float, used: float, pending: float, adjusted: float, remaining: float}
     */
    public function preview(StaffMember $staff, LeaveType $type, ?int $year = null): array
    {
        $on = $year === null ? now()->toDateString() : sprintf('%04d-06-01', $year);
        $policy = $this->planner->policyOn($type, $on);
        [$start] = $policy === null
            ? [sprintf('%04d-01-01', $year ?? (int) now()->year), sprintf('%04d-12-31', $year ?? (int) now()->year)]
            : $this->planner->period($policy, $on);

        $row = CalendarDay::constrain(
            LeaveBalance::query()
                ->where('staff_member_id', $staff->getKey())
                ->where('leave_type_id', $type->getKey()),
            'period_start',
            $start,
        )->first();

        $entitled = (float) ($row->entitled_days ?? $this->planner->entitledDays($staff, $type, $policy, $start));
        $used = (float) ($row->used_days ?? 0);
        $pending = (float) ($row->pending_days ?? 0);
        $adjusted = (float) ($row->adjusted_days ?? 0);

        return [
            'entitled' => $entitled,
            'used' => $used,
            'pending' => $pending,
            'adjusted' => $adjusted,
            'remaining' => $entitled + $adjusted - $used - $pending,
        ];
    }

    private function begin(LeaveRequest $request): array
    {
        $staffId = (int) LeaveRequest::query()->whereKey($request->getKey())->value('staff_member_id');
        $staff = StaffMember::query()->whereKey($staffId)->lockForUpdate()->firstOrFail();
        $locked = $this->lockedRequest($request);

        return [$staff, $locked];
    }

    private function lockPerson(StaffMember $staff): void
    {
        StaffMember::query()->whereKey($staff->getKey())->lockForUpdate()->first();
    }

    private function lockedRequest(LeaveRequest $request): LeaveRequest
    {
        $locked = LeaveRequest::query()->whereKey($request->getKey())->lockForUpdate()->first();

        if ($locked === null) {
            throw new AttendanceException('already_decided', 'This request has already been decided.');
        }

        return $locked;
    }

    private function assertNoOverlap(StaffMember $staff, string $starts, string $ends, ?int $ignoreId = null, bool $approvedOnly = false): void
    {
        $statuses = $approvedOnly
            ? [LeaveStatus::Approved->value]
            : [LeaveStatus::Submitted->value, LeaveStatus::Approved->value];

        $overlap = LeaveRequest::query()
            ->where('staff_member_id', $staff->getKey())
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->whereIn('status', $statuses)
            ->where('starts_on', '<', CalendarDay::following($ends))
            ->where('ends_on', '>=', $starts)
            ->lockForUpdate()
            ->exists();

        if ($overlap) {
            throw new AttendanceException('leave_overlap', 'Those dates overlap an existing leave request.');
        }
    }

    private function assertCanDecide(User $actor, StaffMember $staff, ?LeavePolicy $policy): void
    {
        $self = (int) $actor->staffMember?->getKey() === (int) $staff->getKey();

        if ($self && ! ($policy?->allow_self_approval ?? false)) {
            throw new AttendanceException('self_approval', 'You cannot decide your own leave request.');
        }

        if ($actor->can('leave.policy')) {
            return;
        }

        $mode = $policy?->approval_mode ?? LeaveApprovalMode::AnyApprover;

        if ($mode === LeaveApprovalMode::LineManager && $staff->reports_to_id !== null) {
            $managerUser = $staff->reportsTo()->value('user_id');

            if ((int) $managerUser !== (int) $actor->getKey()) {
                throw new AttendanceException('not_approver', 'This request is waiting for the line manager.');
            }
        }

        if ($mode === LeaveApprovalMode::DepartmentHead) {
            $staff->loadMissing('department');
            $headId = $staff->department?->head_staff_member_id;

            if ($headId !== null) {
                $headUser = StaffMember::query()->whereKey($headId)->value('user_id');

                if ((int) $headUser !== (int) $actor->getKey()) {
                    throw new AttendanceException('not_approver', 'This request is waiting for the department head.');
                }
            }
        }
    }

    private function consumePending(StaffMember $staff, LeaveRequest $request): void
    {
        if (! $request->leaveType->requires_balance) {
            return;
        }

        $policy = $request->policy;
        $balance = $this->lockedBalance($staff, $request->leaveType, $policy, $request->starts_on->toDateString());
        $days = $request->dayCount();
        $held = min($days, (int) $balance->pending_days);

        if ($held > 0) {
            $balance->decrement('pending_days', $held);
            $balance->refresh();
        }

        $available = (int) $balance->entitled_days + (int) $balance->adjusted_days - (int) $balance->used_days;

        if ($available < $days) {
            throw new AttendanceException('insufficient_balance', 'The remaining leave balance does not cover these dates.');
        }

        $balance->increment('used_days', $days);
    }

    private function releasePending(StaffMember $staff, LeaveRequest $request): void
    {
        if (! $request->leaveType()->value('requires_balance')) {
            return;
        }

        $request->loadMissing('leaveType', 'policy');
        $policy = $request->policy;
        $on = $request->starts_on->toDateString();
        $balance = $policy === null
            ? CalendarDay::constrain(
                LeaveBalance::query()
                    ->where('staff_member_id', $staff->getKey())
                    ->where('leave_type_id', $request->leave_type_id),
                'period_start',
                substr($on, 0, 4).'-01-01',
            )->lockForUpdate()->first()
            : $this->lockedBalance($staff, $request->leaveType, $policy, $on);

        if ($balance === null) {
            return;
        }

        $release = min($request->dayCount(), (int) $balance->pending_days);

        if ($release > 0) {
            $balance->decrement('pending_days', $release);
        }
    }

    /**
     * @param  list<string>  $dates
     */
    private function retargetPending(StaffMember $staff, LeaveRequest $request, array $dates, ?LeavePolicy $policy, string $starts): void
    {
        if (! $request->leaveType->requires_balance) {
            return;
        }

        $balance = $this->lockedBalance($staff, $request->leaveType, $policy, $starts);
        $previous = $request->dayCount();
        $next = count($dates);
        $held = min($previous, (int) $balance->pending_days);

        if ($held > 0) {
            $balance->decrement('pending_days', $held);
            $balance->refresh();
        }

        if ($balance->available() < $next) {
            throw new AttendanceException('insufficient_balance', 'The remaining leave balance does not cover these dates.');
        }

        $balance->increment('pending_days', $next);
    }

    private function lockedBalance(StaffMember $staff, LeaveType $type, ?LeavePolicy $policy, string $on): LeaveBalance
    {
        if ($policy === null) {
            $start = substr($on, 0, 4).'-01-01';
            $end = substr($on, 0, 4).'-12-31';
        } else {
            [$start, $end] = $this->planner->period($policy, $on);
        }

        $balance = CalendarDay::constrain(
            LeaveBalance::query()
                ->where('staff_member_id', $staff->getKey())
                ->where('leave_type_id', $type->getKey()),
            'period_start',
            $start,
        )->lockForUpdate()->first();

        if ($balance !== null) {
            return $balance;
        }

        try {
            return LeaveBalance::query()->create([
                'organization_id' => $staff->organization_id,
                'staff_member_id' => $staff->getKey(),
                'leave_type_id' => $type->getKey(),
                'year' => (int) substr($start, 0, 4),
                'period_start' => $start,
                'period_end' => $end,
                'entitled_days' => $this->planner->entitledDays($staff, $type, $policy, $start),
                'used_days' => 0,
                'pending_days' => 0,
                'adjusted_days' => 0,
            ]);
        } catch (UniqueConstraintViolationException) {
            return CalendarDay::constrain(
                LeaveBalance::query()
                    ->where('staff_member_id', $staff->getKey())
                    ->where('leave_type_id', $type->getKey()),
                'period_start',
                $start,
            )->lockForUpdate()->firstOrFail();
        }
    }

    private function assertSinglePeriod(?LeavePolicy $policy, string $starts, string $ends): void
    {
        if ($policy === null) {
            if (substr($starts, 0, 4) !== substr($ends, 0, 4)) {
                throw new AttendanceException('invalid_dates', 'This request crosses a leave year. Submit one request for each leave year.');
            }

            return;
        }

        [$first] = $this->planner->period($policy, $starts);
        [$second] = $this->planner->period($policy, $ends);

        if ($first !== $second) {
            throw new AttendanceException('invalid_dates', 'This request crosses a leave year. Submit one request for each leave year.');
        }
    }
}
