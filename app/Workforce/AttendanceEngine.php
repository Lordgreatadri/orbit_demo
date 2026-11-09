<?php

namespace App\Workforce;

use App\Enums\AttendanceStatus;
use App\Enums\StaffStatus;
use App\Enums\VerificationMethod;
use App\Models\AttendanceEvent;
use App\Models\AttendanceOtp;
use App\Models\AttendanceRecord;
use App\Models\BoundDevice;
use App\Models\StaffMember;
use App\Models\User;
use App\Models\WorkforcePolicy;
use App\Support\Audit;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class AttendanceEngine
{
    public function __construct(
        private readonly VerificationCentre $verifier,
        private readonly Punctuality $punctuality,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function checkIn(User $actor, StaffMember $staff, array $payload): AttendanceRecord
    {
        $this->guard($actor, $staff);

        if ($staff->status !== StaffStatus::Active) {
            throw new AttendanceException('staff_inactive', 'This person cannot record attendance in their current register status.');
        }

        $staff->loadMissing(['organization', 'primaryLocation']);
        $policy = WorkforcePolicy::resolve($staff->organization);
        $now = now();
        $attempt = null;

        try {
            return DB::transaction(function () use ($actor, $staff, $payload, $policy, $now, &$attempt): AttendanceRecord {
                $today = $now->copy()->timezone($staff->organization->timezone ?: 'UTC')->toDateString();
                $this->closeOlderOpenRecords($staff, $actor, $today);

                $bundle = $this->verifier->assess($actor, $staff, $policy, $payload);
                $attempt = $bundle;
                $this->assertDevice($policy, $bundle);
                $this->assertSessionLocation($bundle);
                $this->assertAccepted($bundle);
                $this->assertDayCoverage($staff, $today);

                $periodKey = $bundle->session
                    ? AttendanceRecord::sessionPeriod((int) $bundle->session->getKey())
                    : AttendanceRecord::dayPeriod($today);

                $existing = AttendanceRecord::query()
                    ->where('staff_member_id', $staff->getKey())
                    ->where('period_key', $periodKey)
                    ->lockForUpdate()
                    ->first();

                if ($existing && $existing->check_in_at !== null && $existing->status->blocksCheckIn()) {
                    throw new AttendanceException('duplicate', $this->duplicateMessage($bundle->session !== null));
                }

                [$status, $late] = $this->punctuality->checkIn($staff, $policy, $now);

                $record = AttendanceRecord::query()->updateOrCreate(
                    [
                        'organization_id' => $staff->organization_id,
                        'staff_member_id' => $staff->getKey(),
                        'period_key' => $periodKey,
                    ],
                    [
                        'user_id' => $actor->getKey(),
                        'department_id' => $staff->department_id,
                        'location_id' => $bundle->location?->getKey() ?? $staff->primary_location_id,
                        'attendance_session_id' => $bundle->session?->getKey(),
                        'work_date' => $today,
                        'check_in_at' => $now,
                        'status' => $status,
                        'source' => 'self',
                        'late_minutes' => $late,
                    ],
                );

                $this->consume($bundle, $actor, $staff);
                $this->writeDecisions($staff, $record, $actor, 'check_in', $bundle);

                Audit::log('attendance.checked_in', $record, [
                    'status' => $status->value,
                    'period_key' => $periodKey,
                    'methods' => array_values(array_map(
                        fn (VerificationDecision $decision) => $decision->method->value,
                        array_filter($bundle->decisions, fn (VerificationDecision $decision) => $decision->accepted),
                    )),
                ], $actor);

                DB::afterCommit(fn () => app(WorkforceNotifier::class)->attendanceRecorded($record));

                return $record;
            });
        } catch (AttendanceException $exception) {
            $this->rememberRejection($staff, $actor, $payload, $exception->reason, $attempt);
            throw $exception;
        } catch (UniqueConstraintViolationException) {
            $this->rememberRejection($staff, $actor, $payload, 'duplicate', $attempt);
            throw new AttendanceException('duplicate', 'Attendance is already recorded for this work period.');
        }
    }

    public function checkOut(User $actor, StaffMember $staff, ?string $recordUuid = null): AttendanceRecord
    {
        $this->guard($actor, $staff);
        $staff->loadMissing('organization');
        $policy = WorkforcePolicy::resolve($staff->organization);
        $now = now();
        $today = $now->copy()->timezone($staff->organization->timezone ?: 'UTC')->toDateString();

        return DB::transaction(function () use ($actor, $staff, $policy, $now, $today, $recordUuid): AttendanceRecord {
            $open = AttendanceRecord::query()
                ->where('staff_member_id', $staff->getKey())
                ->whereDate('work_date', $today)
                ->whereNotNull('check_in_at')
                ->whereNull('check_out_at')
                ->whereIn('status', [
                    AttendanceStatus::Present->value,
                    AttendanceStatus::Late->value,
                    AttendanceStatus::Incomplete->value,
                    AttendanceStatus::Excused->value,
                ])
                ->lockForUpdate()
                ->get();

            $record = $recordUuid === null
                ? null
                : $open->first(fn (AttendanceRecord $candidate): bool => $candidate->uuid === $recordUuid);

            if ($recordUuid !== null && $record === null) {
                $closed = AttendanceRecord::query()
                    ->where('staff_member_id', $staff->getKey())
                    ->where('uuid', $recordUuid)
                    ->whereNotNull('check_out_at')
                    ->exists();

                throw new AttendanceException(
                    $closed ? 'duplicate' : 'not_checked_in',
                    $closed ? 'Check-out is already recorded for this work period.' : 'There is no open check-in to close.',
                );
            }

            if ($record === null && $open->count() > 1) {
                throw new AttendanceException('checkout_ambiguous', 'More than one check-in is open. Close a specific one.');
            }

            $record ??= $open->first();

            if ($record === null) {
                throw new AttendanceException('not_checked_in', 'There is no open check-in to close.');
            }

            $record->forceFill([
                'check_out_at' => $now,
                'early_leave_minutes' => $this->punctuality->earlyLeaveMinutes($staff, $policy, $now),
            ])->save();

            $this->event($staff, $record, $actor, 'check_out', null, 'accepted', 'checked_out', []);
            Audit::log('attendance.checked_out', $record, ['early_leave_minutes' => $record->early_leave_minutes], $actor);

            return $record;
        });
    }

    /**
     * Authorised manual attendance. This is the only path that can set a status without a live verification method.
     *
     * @param  array{work_date: string, status: string, check_in_at?: ?string, check_out_at?: ?string, location_id?: ?int, note?: ?string}  $input
     */
    public function correct(User $actor, StaffMember $staff, array $input): AttendanceRecord
    {
        $this->guard($actor, $staff);
        $staff->loadMissing('organization');
        $tz = $staff->organization->timezone ?: 'UTC';
        $status = AttendanceStatus::from($input['status']);
        $workDate = Carbon::parse($input['work_date'], $tz)->toDateString();
        $checkIn = isset($input['check_in_at']) && $input['check_in_at'] !== ''
            ? Carbon::parse($input['check_in_at'], $tz)->utc()
            : null;
        $checkOut = isset($input['check_out_at']) && $input['check_out_at'] !== ''
            ? Carbon::parse($input['check_out_at'], $tz)->utc()
            : null;

        return DB::transaction(function () use ($actor, $staff, $status, $workDate, $checkIn, $checkOut, $input): AttendanceRecord {
            $policy = WorkforcePolicy::resolve($staff->organization);
            $late = null;

            if ($checkIn !== null && in_array($status, [AttendanceStatus::Present, AttendanceStatus::Late], true)) {
                [$computed, $minutes] = $this->punctuality->checkIn($staff, $policy, $checkIn);
                $status = $computed;
                $late = $minutes;
            }

            $record = AttendanceRecord::query()->updateOrCreate(
                [
                    'organization_id' => $staff->organization_id,
                    'staff_member_id' => $staff->getKey(),
                    'period_key' => AttendanceRecord::dayPeriod($workDate),
                ],
                [
                    'user_id' => $staff->user_id,
                    'department_id' => $staff->department_id,
                    'location_id' => $input['location_id'] ?? $staff->primary_location_id,
                    'work_date' => $workDate,
                    'check_in_at' => $checkIn,
                    'check_out_at' => $checkOut,
                    'status' => $status,
                    'source' => 'manual',
                    'late_minutes' => $late,
                    'note' => $input['note'] ?? null,
                ],
            );

            $this->event($staff, $record, $actor, 'correction', VerificationMethod::Manual->value, 'accepted', 'manual_correction', []);
            Audit::log('attendance.corrected', $record, ['status' => $status->value, 'work_date' => $workDate], $actor);

            return $record;
        });
    }

    public function closeOpenRecords(): int
    {
        $closed = 0;

        AttendanceRecord::withoutTenantScope()
            ->with('organization:id,timezone')
            ->whereNull('check_out_at')
            ->whereIn('status', [AttendanceStatus::Present->value, AttendanceStatus::Late->value])
            ->orderBy('id')
            ->chunkById(200, function ($records) use (&$closed): void {
                foreach ($records as $record) {
                    $today = now($record->organization->timezone ?: 'UTC')->toDateString();
                    if ($record->work_date->toDateString() >= $today) {
                        continue;
                    }

                    $record->update(['status' => AttendanceStatus::Incomplete]);
                    $staff = $record->staffMember()->first() ?? StaffMember::withoutTenantScope()->findOrFail($record->staff_member_id);
                    $this->event($staff, $record, null, 'check_out', null, 'accepted', 'missing_checkout', []);
                    app(WorkforceNotifier::class)->missingCheckout($record);
                    $closed++;
                }
            });

        return $closed;
    }

    private function guard(User $actor, StaffMember $staff): void
    {
        if (! $actor->belongsToOrganization($staff->organization_id)) {
            throw new AttendanceException('tenant_mismatch', 'That person is not in your organisation.');
        }
    }

    private function assertDevice(WorkforcePolicy $policy, VerificationBundle $bundle): void
    {
        if (! $policy->require_device_binding) {
            return;
        }

        $device = $bundle->forMethod(VerificationMethod::Device);

        if (! $device?->accepted) {
            throw new AttendanceException($device->reason ?? 'device_missing', 'This organisation requires a bound device for attendance.');
        }
    }

    private function assertSessionLocation(VerificationBundle $bundle): void
    {
        if ($bundle->session?->require_location) {
            $gps = $bundle->forMethod(VerificationMethod::Gps);
            if (! $gps?->accepted) {
                throw new AttendanceException('location_required', 'This session also requires a location inside the approved area.');
            }
        }
    }

    private function assertAccepted(VerificationBundle $bundle): void
    {
        if ($bundle->acceptedStandalone()) {
            return;
        }

        $wifi = $bundle->forMethod(VerificationMethod::Wifi);
        if ($wifi?->accepted) {
            throw new AttendanceException('wifi_not_standalone', 'A matching Wi-Fi network can support attendance, but it cannot confirm it on its own.');
        }

        $failed = collect($bundle->decisions)->first(fn (VerificationDecision $decision) => ! $decision->accepted);
        $reason = $failed->reason ?? 'verification_failed';

        throw new AttendanceException($reason, $this->message($reason));
    }

    /**
     * Leave and an excused day cover the calendar day. A present check-in does not.
     */
    private function assertDayCoverage(StaffMember $staff, string $today): void
    {
        $covered = AttendanceRecord::query()
            ->where('staff_member_id', $staff->getKey())
            ->whereDate('work_date', $today)
            ->whereIn('status', [AttendanceStatus::OnLeave->value, AttendanceStatus::Excused->value])
            ->lockForUpdate()
            ->first();

        if ($covered?->status === AttendanceStatus::OnLeave) {
            throw new AttendanceException('on_leave', 'Approved leave already covers this day.');
        }

        if ($covered?->status === AttendanceStatus::Excused) {
            throw new AttendanceException('excused', 'This day is already recorded as excused.');
        }
    }

    private function duplicateMessage(bool $session): string
    {
        return $session
            ? 'Attendance is already recorded for this session.'
            : 'Attendance is already recorded for this work period.';
    }

    private function consume(VerificationBundle $bundle, User $actor, StaffMember $staff): void
    {
        foreach ($bundle->decisions as $decision) {
            if (! $decision->accepted) {
                continue;
            }

            if ($decision->otpId !== null) {
                $updated = AttendanceOtp::query()->whereKey($decision->otpId)->whereNull('consumed_at')->update(['consumed_at' => now()]);

                if ($updated !== 1) {
                    throw new AttendanceException('otp_invalid', $this->message('otp_invalid'));
                }
            }

            if ($decision->reason === 'device_enrolled') {
                $hash = (string) ($decision->evidence['device_hash'] ?? '');
                if ($hash !== '') {
                    BoundDevice::query()->create([
                        'organization_id' => $staff->organization_id,
                        'user_id' => $actor->getKey(),
                        'device_hash' => $hash,
                        'label' => 'Enrolled at check-in',
                        'confirmed_at' => now(),
                    ]);
                }
            }
        }
    }

    private function writeDecisions(StaffMember $staff, AttendanceRecord $record, User $actor, string $type, VerificationBundle $bundle): void
    {
        foreach ($bundle->decisions as $decision) {
            $this->event(
                $staff,
                $record,
                $actor,
                $type,
                $decision->method->value,
                $decision->accepted ? 'accepted' : 'rejected',
                $decision->reason,
                $decision->evidence,
            );
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function rememberRejection(StaffMember $staff, User $actor, array $payload, string $reason, ?VerificationBundle $bundle = null): void
    {
        if ($bundle !== null && $bundle->decisions !== []) {
            foreach ($bundle->decisions as $decision) {
                $evidence = $decision->evidence;
                $evidence['attendance_result'] = 'rejected';
                $evidence['rejection_reason'] = $reason;

                $this->event(
                    $staff,
                    null,
                    $actor,
                    'check_in',
                    $decision->method->value,
                    'rejected',
                    $decision->reason,
                    $evidence,
                );
            }

            return;
        }

        $method = VerificationMethod::tryFrom((string) (($payload['methods'][0] ?? '')));
        $this->event($staff, null, $actor, 'check_in', $method?->value, 'rejected', $reason, [
            'attendance_result' => 'rejected',
            'rejection_reason' => $reason,
        ]);
    }

    private function closeOlderOpenRecords(StaffMember $staff, User $actor, string $today): void
    {
        AttendanceRecord::query()
            ->where('staff_member_id', $staff->getKey())
            ->whereNull('check_out_at')
            ->whereIn('status', [AttendanceStatus::Present->value, AttendanceStatus::Late->value])
            ->whereDate('work_date', '<', $today)
            ->lockForUpdate()
            ->get()
            ->each(function (AttendanceRecord $record) use ($staff, $actor): void {
                $record->update(['status' => AttendanceStatus::Incomplete]);
                $this->event($staff, $record, $actor, 'check_out', null, 'accepted', 'missing_checkout', []);
                DB::afterCommit(fn () => app(WorkforceNotifier::class)->missingCheckout($record));
            });
    }

    /**
     * Store one verification decision on the attendance event trail.
     * Accepted and rejected readings are both kept.
     */
    public function recordVerification(StaffMember $staff, ?AttendanceRecord $record, User $actor, string $type, VerificationDecision $decision): void
    {
        $this->event(
            $staff,
            $record,
            $actor,
            $type,
            $decision->method->value,
            $decision->accepted ? 'accepted' : 'rejected',
            $decision->reason,
            $decision->evidence,
        );
    }

    /**
     * @param  array<string, mixed>  $evidence
     */
    private function event(StaffMember $staff, ?AttendanceRecord $record, ?User $actor, string $type, ?string $method, string $outcome, string $reason, array $evidence): void
    {
        unset($evidence['device_hash'], $evidence['token'], $evidence['otp'], $evidence['bssid']);

        if ($evidence !== []) {
            $evidence['validation_result'] = $outcome === 'accepted' ? 'accepted' : 'rejected';
        }

        AttendanceEvent::query()->create([
            'organization_id' => $staff->organization_id,
            'attendance_record_id' => $record?->getKey(),
            'staff_member_id' => $staff->getKey(),
            'actor_user_id' => $actor?->getKey(),
            'type' => $type,
            'method' => $method,
            'outcome' => $outcome,
            'reason' => $reason,
            'evidence' => $evidence === [] ? null : $evidence,
        ]);
    }

    public function message(string $reason): string
    {
        return match ($reason) {
            'invalid_token' => 'This attendance code is not valid.',
            'token_expired' => 'This attendance code has expired.',
            'session_closed' => 'This attendance session is not active.',
            'outside_geofence' => 'The reported location is outside the approved area.',
            'inaccurate_location' => 'The location reading is not accurate enough to accept.',
            'stale_location' => 'The location reading is too old.',
            'location_unavailable' => 'A current location reading is required.',
            'location_not_configured' => 'This location has no approved geofence.',
            'location_required' => 'This session also requires a location inside the approved area.',
            'location_conflict' => 'The verification does not match the attendance location.',
            'beacon_unknown' => 'That beacon is not registered for this organisation.',
            'wifi_unknown' => 'That Wi-Fi network is not approved.',
            'wifi_not_standalone' => 'A matching Wi-Fi network can support attendance, but it cannot confirm it on its own.',
            'method_not_enabled' => 'That verification method is not available.',
            'method_disabled' => 'That verification method is turned off for this organisation.',
            'manual_not_allowed' => 'Office attendance needs an approved verification method. A button press is not enough.',
            'otp_invalid', 'otp_disabled' => 'That one-time code cannot be used.',
            'duplicate' => 'Attendance is already recorded for this work period.',
            'excused' => 'This day is already recorded as excused.',
            'checkout_ambiguous' => 'More than one check-in is open. Close a specific one.',
            'on_leave' => 'Approved leave already covers this day.',
            'device_missing', 'device_mismatch' => 'This organisation requires a bound device for attendance.',
            default => 'Attendance could not be recorded.',
        };
    }
}
