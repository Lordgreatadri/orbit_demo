<?php

namespace App\Workforce;

use App\Enums\AttendanceStatus;
use App\Enums\LeaveStatus;
use App\Models\AttendanceRecord;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\StaffMember;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

final class LeaveService
{
    public function submit(User $actor, StaffMember $staff, LeaveType $type, string $starts, string $ends, string $reason, ?UploadedFile $document): LeaveRequest
    {
        if ($ends < $starts) {
            throw new AttendanceException('invalid_dates', 'The leave end date must be on or after the start date.');
        }

        $overlap = LeaveRequest::query()
            ->where('staff_member_id', $staff->getKey())
            ->whereIn('status', [LeaveStatus::Submitted->value, LeaveStatus::Approved->value])
            ->where('starts_on', '<=', $ends)
            ->where('ends_on', '>=', $starts)
            ->exists();

        if ($overlap) {
            throw new AttendanceException('leave_overlap', 'Those dates overlap an existing leave request.');
        }

        $days = (int) abs(now()->parse($starts)->diffInDays(now()->parse($ends))) + 1;

        if ($type->requires_balance) {
            $balance = $this->balance($staff, $type, (int) now()->parse($starts)->year);
            $remaining = (float) $balance->entitled_days - (float) $balance->used_days;

            if ($remaining <= 0) {
                throw new AttendanceException('insufficient_balance', 'You have no remaining days for this leave type.');
            }

            if ($remaining < $days) {
                throw new AttendanceException('insufficient_balance', 'The remaining leave balance does not cover these dates.');
            }
        }

        $path = $document?->store('leave/'.$staff->organization_id);

        $request = LeaveRequest::query()->create([
            'organization_id' => $staff->organization_id,
            'staff_member_id' => $staff->getKey(),
            'leave_type_id' => $type->getKey(),
            'starts_on' => $starts,
            'ends_on' => $ends,
            'reason' => $reason,
            'status' => LeaveStatus::Submitted,
            'document_path' => $path,
        ]);

        Audit::log('leave.submitted', $request, ['days' => $days], $actor);
        app(WorkforceNotifier::class)->leaveSubmitted($request);

        return $request;
    }

    public function decide(User $actor, LeaveRequest $request, bool $approve, ?string $note): void
    {
        DB::transaction(function () use ($actor, $request, $approve, $note): void {
            $locked = LeaveRequest::query()->whereKey($request->getKey())->lockForUpdate()->first();

            if ($locked === null || $locked->status !== LeaveStatus::Submitted) {
                throw new AttendanceException('already_decided', 'This request has already been decided.');
            }

            $locked->update([
                'status' => $approve ? LeaveStatus::Approved : LeaveStatus::Rejected,
                'decided_by' => $actor->getKey(),
                'decision_note' => $note,
                'decided_at' => now(),
            ]);

            if ($approve) {
                $locked->load('leaveType', 'staffMember');
                if ($locked->leaveType->requires_balance) {
                    $balance = $this->lockedBalance($locked->staffMember, $locked->leaveType, (int) $locked->starts_on->year);
                    $remaining = (int) $balance->entitled_days - (int) $balance->used_days;

                    if ($remaining < $locked->dayCount()) {
                        throw new AttendanceException('insufficient_balance', 'The remaining leave balance does not cover these dates.');
                    }

                    $balance->increment('used_days', $locked->dayCount());
                }

                $cursor = $locked->starts_on->copy();
                while ($cursor->lte($locked->ends_on)) {
                    $existing = AttendanceRecord::query()
                        ->where('staff_member_id', $locked->staff_member_id)
                        ->whereDate('work_date', $cursor->toDateString())
                        ->whereNotNull('check_in_at')
                        ->first();

                    if ($existing === null) {
                        AttendanceRecord::query()->updateOrCreate(
                            [
                                'organization_id' => $locked->organization_id,
                                'staff_member_id' => $locked->staff_member_id,
                                'period_key' => AttendanceRecord::dayPeriod($cursor->toDateString()),
                            ],
                            [
                                'work_date' => $cursor->toDateString(),
                                'department_id' => $locked->staffMember->department_id,
                                'location_id' => $locked->staffMember->primary_location_id,
                                'status' => AttendanceStatus::OnLeave,
                                'source' => 'manual',
                                'user_id' => $locked->staffMember->user_id,
                            ],
                        );
                    }

                    $cursor->addDay();
                }
            }

            Audit::log($approve ? 'leave.approved' : 'leave.rejected', $locked, [], $actor);
        });

        app(WorkforceNotifier::class)->leaveDecided($request->fresh());
    }

    /**
     * Entitlement for a year without creating a balance row.
     *
     * @return array{entitled: float, used: float, remaining: float}
     */
    public function preview(StaffMember $staff, LeaveType $type, int $year): array
    {
        $row = LeaveBalance::query()
            ->where('staff_member_id', $staff->getKey())
            ->where('leave_type_id', $type->getKey())
            ->where('year', $year)
            ->first();

        $entitled = (float) ($row->entitled_days ?? $type->default_days ?? 0);
        $used = (float) ($row->used_days ?? 0);

        return [
            'entitled' => $entitled,
            'used' => $used,
            'remaining' => $entitled - $used,
        ];
    }

    private function lockedBalance(StaffMember $staff, LeaveType $type, int $year): LeaveBalance
    {
        $balance = LeaveBalance::query()
            ->where('staff_member_id', $staff->getKey())
            ->where('leave_type_id', $type->getKey())
            ->where('year', $year)
            ->lockForUpdate()
            ->first();

        if ($balance !== null) {
            return $balance;
        }

        try {
            return LeaveBalance::query()->create([
                'organization_id' => $staff->organization_id,
                'staff_member_id' => $staff->getKey(),
                'leave_type_id' => $type->getKey(),
                'year' => $year,
                'entitled_days' => $type->default_days ?? 0,
                'used_days' => 0,
            ]);
        } catch (UniqueConstraintViolationException) {
            return LeaveBalance::query()
                ->where('staff_member_id', $staff->getKey())
                ->where('leave_type_id', $type->getKey())
                ->where('year', $year)
                ->lockForUpdate()
                ->firstOrFail();
        }
    }

    private function balance(StaffMember $staff, LeaveType $type, int $year): LeaveBalance
    {
        return LeaveBalance::query()->firstOrCreate(
            [
                'staff_member_id' => $staff->getKey(),
                'leave_type_id' => $type->getKey(),
                'year' => $year,
            ],
            [
                'organization_id' => $staff->organization_id,
                'entitled_days' => $type->default_days ?? 0,
                'used_days' => 0,
            ],
        );
    }
}
