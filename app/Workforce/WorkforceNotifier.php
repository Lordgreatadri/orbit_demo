<?php

namespace App\Workforce;

use App\Contracts\PushNotifier;
use App\Enums\AttendanceStatus;
use App\Enums\FieldActivityStatus;
use App\Enums\LeaveApprovalMode;
use App\Enums\LeaveStatus;
use App\Enums\ShiftStatus;
use App\Enums\UserStatus;
use App\Integrations\Sms\SmsTransmitter;
use App\Jobs\Workforce\DeliverWorkforceNotice;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\CommunicationPolicy;
use App\Models\FieldActivity;
use App\Models\FieldAssignment;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\NotificationPreference;
use App\Models\NotificationTemplate;
use App\Models\Organization;
use App\Models\ShiftAssignment;
use App\Models\SmsProvider;
use App\Models\StaffMember;
use App\Models\User;
use App\Models\WorkforceNoticeDelivery;
use App\Models\WorkforcePolicy;
use App\Notifications\WorkforceMessage;
use App\Support\CalendarDay;
use App\Tenancy\Origin;
use App\Workforce\Communication\DeliveryFailure;
use App\Workforce\Communication\NotificationCopy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Queues one workforce notice after the surrounding transaction commits.
 * The leave, schedule, or attendance row is already stored before this runs.
 * A provider failure is recorded on the delivery row and does not undo that row.
 */
final class WorkforceNotifier
{
    public const LOW_BALANCE_AT = 2;

    public const PENDING_LEAVE_AFTER_DAYS = 2;

    public const BALANCE_EXPIRING_WITHIN_DAYS = 14;

    /**
     * Check-in failures worth telling the person about. Expired codes and
     * duplicate taps are not included.
     *
     * @var list<string>
     */
    private const REJECTION_REASONS = [
        'outside_geofence',
        'on_leave',
        'device_mismatch',
        'device_missing',
        'manual_not_allowed',
        'location_required',
        'method_not_enabled',
        'method_disabled',
    ];

    /** @var array<int, Collection<string, NotificationTemplate>> */
    private array $templates = [];

    public function __construct(private readonly PushNotifier $push) {}

    public function attendanceRecorded(AttendanceRecord $record): void
    {
        $record->loadMissing('staffMember.user', 'organization');
        $user = $record->staffMember?->user;

        if ($user === null) {
            return;
        }

        $late = $record->status === AttendanceStatus::Late;
        $this->send(
            $user,
            $late ? 'attendance.late' : 'attendance.confirmed',
            $late ? 'Late arrival recorded' : 'Attendance recorded',
            $late
                ? 'You were marked late by '.$record->late_minutes.' minutes.'
                : 'Your check-in was recorded as '.$record->status->label().'.',
            Origin::url($record->organization, '/org/attendance/mine'),
            'My attendance',
            'attendance-'.$record->getKey().'-'.($late ? 'late' : 'confirmed'),
        );
    }

    public function attendanceRejected(StaffMember $staff, User $actor, string $reason): void
    {
        if (! in_array($reason, self::REJECTION_REASONS, true)) {
            return;
        }

        $staff->loadMissing('organization', 'user');
        $user = $staff->user;
        $organization = $staff->organization;

        if ($user === null || $organization === null || (int) $user->getKey() !== (int) $actor->getKey()) {
            return;
        }

        if ((int) $user->organization_id !== (int) $organization->getKey()) {
            return;
        }

        $date = now($organization->timezone ?: 'UTC')->toDateString();

        $this->send(
            $user,
            'attendance.rejected',
            'Attendance not recorded',
            app(AttendanceEngine::class)->message($reason),
            Origin::url($organization, '/org/attendance/mine'),
            'My attendance',
            'attendance-rejected-'.$staff->getKey().'-'.$date.'-'.$reason,
            ['status' => $reason],
        );
    }

    public function sessionOpened(AttendanceSession $session): void
    {
        $session->loadMissing('organization', 'location');
        $organization = $session->organization;

        if ($organization === null) {
            return;
        }

        $timezone = $organization->timezone ?: 'UTC';
        $start = $session->starts_at->timezone($timezone);
        $end = $session->ends_at->timezone($timezone);
        $place = $session->location?->name ?? 'any location';
        $when = $start->isSameDay($end)
            ? $start->format('D j M Y, H:i').'–'.$end->format('H:i')
            : $start->format('D j M Y, H:i').' – '.$end->format('D j M Y, H:i');
        $title = $session->starts_at->lte(now())
            ? 'Check in: '.$session->name
            : 'Upcoming check-in: '.$session->name;
        $url = Origin::url($organization, '/org/attendance/mine#opened-sessions');
        $body = $session->name.' is at '.$place.' ('.$when.'). Open the live code while you are signed in: '.$url.' The code changes every '.$session->qr_ttl_seconds.' seconds, so this message does not include it.';

        User::query()
            ->where('organization_id', $session->organization_id)
            ->where('status', UserStatus::Active->value)
            ->permission('attendance.record')
            ->orderBy('id')
            ->chunkById(100, function ($users) use ($session, $title, $body, $url, $place, $when): void {
                foreach ($users as $user) {
                    $this->send(
                        $user,
                        'session.opened',
                        $title,
                        $body,
                        $url,
                        'Open the check-in code',
                        'session-opened-'.$session->getKey().'-'.$user->getKey(),
                        [
                            'session_name' => $session->name,
                            'place' => $place,
                            'when' => $when,
                        ],
                    );
                }
            });
    }

    public function schedulePublished(ShiftAssignment $assignment): void
    {
        $assignment->loadMissing('staffMember.user', 'shift', 'organization');
        $user = $assignment->staffMember?->user;

        if ($user === null || $assignment->shift === null || $assignment->organization === null) {
            return;
        }

        $date = $assignment->work_date->format('j F Y');

        $this->send(
            $user,
            'schedule.published',
            'Schedule assigned',
            $assignment->shift->name.' is on your schedule for '.$date.'.',
            Origin::url($assignment->organization, '/org/schedules'),
            'View schedule',
            'schedule-published-'.$assignment->getKey(),
            [
                'shift_name' => $assignment->shift->name,
                'schedule_date' => $date,
            ],
        );
    }

    public function scheduleRemoved(ShiftAssignment $assignment): void
    {
        $assignment->loadMissing('staffMember.user', 'shift', 'organization');
        $user = $assignment->staffMember?->user;

        if ($user === null || $assignment->shift === null || $assignment->organization === null) {
            return;
        }

        $date = $assignment->work_date?->format('j F Y') ?? '';

        $this->send(
            $user,
            'schedule.changed',
            'Schedule changed',
            $assignment->shift->name.' was removed from your schedule for '.$date.'.',
            Origin::url($assignment->organization, '/org/schedules'),
            'View schedule',
            'schedule-removed-'.$assignment->getKey(),
            [
                'shift_name' => $assignment->shift->name,
                'schedule_date' => $date,
            ],
        );
    }

    public function fieldAssigned(FieldAssignment $assignment): void
    {
        $assignment->loadMissing('staffMember.user', 'organization');
        $user = $assignment->staffMember?->user;

        if ($user === null || $assignment->organization === null) {
            return;
        }

        $this->send(
            $user,
            'field.assigned',
            'Field assignment',
            $assignment->title.' from '.$assignment->starts_on->toFormattedDateString().'.',
            Origin::url($assignment->organization, '/org/field'),
            'Field work',
            'field-assigned-'.$assignment->getKey(),
        );
    }

    public function leaveSubmitted(LeaveRequest $request): void
    {
        $this->tellApprovers(
            $request,
            'leave.submitted',
            'Leave request submitted',
            $this->leaveSummary($request, 'requested'),
            'leave-submitted-'.$request->getKey(),
        );
    }

    public function leaveAmended(LeaveRequest $request): void
    {
        $this->tellApprovers(
            $request,
            'leave.amended',
            'Leave request updated',
            $this->leaveSummary($request, 'updated'),
            'leave-amended-'.$request->getKey().'-'.$request->starts_on->toDateString().'-'.$request->ends_on->toDateString(),
        );
    }

    public function missingCheckout(AttendanceRecord $record): void
    {
        $record->loadMissing('organization');
        $user = $record->user_id ? User::query()->find($record->user_id) : null;
        $user ??= StaffMember::withoutTenantScope()->find($record->staff_member_id)?->user;
        $organization = $record->organization;

        if ($user === null || $organization === null || (int) $user->organization_id !== (int) $organization->getKey()) {
            return;
        }

        $this->send(
            $user,
            'attendance.missing_checkout',
            'Missing check-out',
            'An open attendance record was marked incomplete because check-out was not recorded.',
            Origin::url($organization, '/org/attendance/mine'),
            'My attendance',
            'attendance-missing-'.$record->getKey(),
        );
    }

    public function remindDueShifts(): int
    {
        $sent = 0;

        Organization::query()->orderBy('id')->each(function (Organization $organization) use (&$sent): void {
            $policy = CommunicationPolicy::forOrganization($organization);

            if (! $policy->schedule_notifications_enabled || ! $policy->attendance_reminders_enabled) {
                return;
            }

            $tomorrow = now($organization->timezone ?: 'UTC')->addDay()->toDateString();

            CalendarDay::constrain(
                ShiftAssignment::withoutTenantScope()->where('organization_id', $organization->getKey()),
                'work_date',
                $tomorrow,
            )
                ->whereNull('reminded_at')
                ->whereHas('shift', fn ($query) => $query->where('status', ShiftStatus::Published->value))
                ->with(['staffMember.user', 'shift', 'organization'])
                ->orderBy('id')
                ->chunkById(100, function ($assignments) use (&$sent): void {
                    foreach ($assignments as $assignment) {
                        $user = $assignment->staffMember?->user;

                        if ($user === null || $assignment->shift === null) {
                            continue;
                        }

                        $claimed = ShiftAssignment::withoutTenantScope()
                            ->whereKey($assignment->getKey())
                            ->whereNull('reminded_at')
                            ->update(['reminded_at' => now()]);

                        if ($claimed !== 1 || ! $this->wants($user, 'schedule.reminder')) {
                            continue;
                        }

                        $date = $assignment->work_date->format('j F Y');
                        $this->send(
                            $user,
                            'schedule.reminder',
                            'Upcoming schedule',
                            $assignment->shift->name.' is on your schedule for '.$date.'.',
                            Origin::url($assignment->organization, '/org/schedules'),
                            'View schedule',
                            'schedule-reminder-'.$assignment->getKey(),
                            [
                                'shift_name' => $assignment->shift->name,
                                'schedule_date' => $date,
                            ],
                        );
                        $sent++;
                    }
                });
        });

        return $sent;
    }

    public function remindMissedAttendance(): int
    {
        $sent = 0;

        Organization::query()->orderBy('id')->each(function (Organization $organization) use (&$sent): void {
            if (! CommunicationPolicy::forOrganization($organization)->attendance_reminders_enabled) {
                return;
            }

            $timezone = $organization->timezone ?: 'UTC';
            $yesterday = now($timezone)->subDay()->toDateString();
            $recorded = AttendanceRecord::withoutTenantScope()
                ->where('organization_id', $organization->getKey())
                ->tap(fn ($query) => CalendarDay::constrain($query, 'work_date', $yesterday))
                ->pluck('staff_member_id')
                ->map(fn ($id): int => (int) $id)
                ->all();
            $onLeave = LeaveRequest::withoutTenantScope()
                ->where('organization_id', $organization->getKey())
                ->where('status', LeaveStatus::Approved->value)
                ->where('starts_on', '<=', $yesterday)
                ->where('ends_on', '>=', $yesterday)
                ->pluck('staff_member_id')
                ->map(fn ($id): int => (int) $id)
                ->all();

            CalendarDay::constrain(
                ShiftAssignment::withoutTenantScope()->where('organization_id', $organization->getKey()),
                'work_date',
                $yesterday,
            )
                ->whereHas('shift', fn ($query) => $query->where('status', ShiftStatus::Published->value))
                ->with(['staffMember.user', 'staffMember.reportsTo.user', 'shift', 'organization'])
                ->orderBy('id')
                ->chunkById(100, function ($assignments) use (&$sent, $recorded, $onLeave, $organization): void {
                    foreach ($assignments as $assignment) {
                        $staff = $assignment->staffMember;
                        $user = $staff?->user;

                        if ($staff === null || $user === null || $assignment->shift === null) {
                            continue;
                        }

                        if (in_array((int) $staff->getKey(), $recorded, true) || in_array((int) $staff->getKey(), $onLeave, true)) {
                            continue;
                        }

                        if ((int) $user->organization_id !== (int) $organization->getKey()) {
                            continue;
                        }

                        $date = $assignment->work_date->format('j F Y');

                        if ($this->wants($user, 'attendance.missed')) {
                            $this->send(
                                $user,
                                'attendance.missed',
                                'Missed attendance',
                                'No attendance was recorded for '.$assignment->shift->name.' on '.$date.'.',
                                Origin::url($organization, '/org/attendance/mine'),
                                'My attendance',
                                'attendance-missed-'.$assignment->getKey(),
                                ['shift_name' => $assignment->shift->name, 'schedule_date' => $date],
                            );
                            $sent++;
                        }

                        $manager = $staff->reportsTo?->user;

                        if ($manager === null || (int) $manager->organization_id !== (int) $organization->getKey() || (int) $manager->getKey() === (int) $user->getKey()) {
                            continue;
                        }

                        if (! $this->wants($manager, 'attendance.missed')) {
                            continue;
                        }

                        $this->send(
                            $manager,
                            'attendance.missed',
                            'Missed attendance',
                            $staff->name.' has no attendance record for '.$assignment->shift->name.' on '.$date.'.',
                            Origin::url($organization, '/org/attendance'),
                            'Attendance records',
                            'attendance-missed-'.$assignment->getKey().'-manager-'.$manager->getKey(),
                            ['person_name' => $staff->name, 'shift_name' => $assignment->shift->name, 'schedule_date' => $date],
                        );
                        $sent++;
                    }
                });
        });

        return $sent;
    }

    public function remindPendingLeave(): int
    {
        $sent = 0;
        $cutoff = now()->subDays(self::PENDING_LEAVE_AFTER_DAYS);

        Organization::query()->orderBy('id')->each(function (Organization $organization) use (&$sent, $cutoff): void {
            if (! CommunicationPolicy::forOrganization($organization)->leave_notifications_enabled) {
                return;
            }

            LeaveRequest::withoutTenantScope()
                ->where('organization_id', $organization->getKey())
                ->where('status', LeaveStatus::Submitted->value)
                ->where('created_at', '<=', $cutoff)
                ->with(['staffMember.user', 'staffMember.department', 'leaveType', 'policy', 'organization'])
                ->orderBy('id')
                ->chunkById(50, function ($requests) use (&$sent): void {
                    foreach ($requests as $request) {
                        $this->approvers($request)->orderBy('id')->chunkById(50, function ($users) use ($request, &$sent): void {
                            foreach ($users as $user) {
                                if (! $this->wants($user, 'leave.pending_reminder')) {
                                    continue;
                                }

                                $this->send(
                                    $user,
                                    'leave.pending_reminder',
                                    'Leave is still waiting',
                                    $this->leaveSummary($request, 'is still waiting for a decision on'),
                                    Origin::url($request->organization, '/org/leave'),
                                    'Review leave',
                                    'leave-pending-'.$request->getKey().'-'.$user->getKey(),
                                    $this->leaveVars($request),
                                );
                                $sent++;
                            }
                        });
                    }
                });
        });

        return $sent;
    }

    public function remindExpiringBalances(): int
    {
        $sent = 0;

        Organization::query()->orderBy('id')->each(function (Organization $organization) use (&$sent): void {
            if (! CommunicationPolicy::forOrganization($organization)->leave_notifications_enabled) {
                return;
            }

            $today = now($organization->timezone ?: 'UTC')->toDateString();
            $until = now($organization->timezone ?: 'UTC')->addDays(self::BALANCE_EXPIRING_WITHIN_DAYS)->toDateString();

            LeaveBalance::withoutTenantScope()
                ->where('organization_id', $organization->getKey())
                ->where('period_end', '>=', $today)
                ->where('period_end', '<=', $until)
                ->with(['staffMember.user', 'leaveType', 'organization'])
                ->orderBy('id')
                ->chunkById(100, function ($balances) use (&$sent): void {
                    foreach ($balances as $balance) {
                        if ($this->leaveBalanceNotice($balance, expiring: true)) {
                            $sent++;
                        }
                    }
                });
        });

        return $sent;
    }

    public function flagDelayedField(): int
    {
        $flagged = 0;

        FieldActivity::withoutTenantScope()
            ->whereNull('ended_at')
            ->where('status', FieldActivityStatus::Active->value)
            ->with(['staffMember.user', 'organization', 'assignment'])
            ->orderBy('id')
            ->each(function (FieldActivity $activity) use (&$flagged): void {
                if ($activity->organization === null) {
                    return;
                }

                $policy = WorkforcePolicy::resolve($activity->organization);
                $quiet = $activity->last_reported_at === null
                    || $activity->last_reported_at->lt(now()->subMinutes($policy->field_delay_minutes));

                if (! $quiet) {
                    return;
                }

                $activity->update(['status' => FieldActivityStatus::Delayed]);
                $user = $activity->staffMember?->user;

                if ($user !== null) {
                    $this->send(
                        $user,
                        'field.alert',
                        'Field activity needs an update',
                        ($activity->assignment?->title ?? 'A field activity').' has not reported within the allowed interval.',
                        Origin::url($activity->organization, '/org/field'),
                        'Field work',
                        'field-alert-'.$activity->getKey().'-'.now($activity->organization->timezone ?: 'UTC')->toDateString(),
                    );
                }

                $flagged++;
            });

        return $flagged;
    }

    public function leaveDecided(LeaveRequest $request): void
    {
        $request->loadMissing('staffMember.user', 'leaveType', 'organization');

        if ($request->status === LeaveStatus::Withdrawn) {
            $this->tellApprovers(
                $request,
                'leave.decided',
                'Leave request withdrawn',
                $this->leaveSummary($request, 'withdrew'),
                'leave-decided-'.$request->getKey().'-'.$request->status->value,
            );

            return;
        }

        $user = $request->staffMember?->user;
        $organization = $request->organization;

        if ($user === null || $organization === null || (int) $user->organization_id !== (int) $organization->getKey()) {
            return;
        }

        [$title, $body] = match ($request->status) {
            LeaveStatus::Approved => ['Leave approved', $this->leaveOutcome($request, 'has been approved')],
            LeaveStatus::Rejected => ['Leave not approved', $this->leaveOutcome($request, 'was not approved')],
            LeaveStatus::Cancelled => ['Approved leave cancelled', $this->leaveOutcome($request, 'has been cancelled')],
            default => ['Leave '.$request->status->label(), $this->leaveOutcome($request, 'was '.$request->status->label())],
        };

        $this->send(
            $user,
            'leave.decided',
            $title,
            $body,
            Origin::url($organization, '/org/leave'),
            'View leave',
            'leave-decided-'.$request->getKey().'-'.$request->status->value,
            $this->leaveVars($request),
        );
    }

    public function warnLowBalance(LeaveRequest $request): void
    {
        $request->loadMissing('organization');
        $on = $request->starts_on->toDateString();
        $balance = LeaveBalance::withoutTenantScope()
            ->where('organization_id', $request->organization_id)
            ->where('staff_member_id', $request->staff_member_id)
            ->where('leave_type_id', $request->leave_type_id)
            ->where('period_start', '<=', $on)
            ->where('period_end', '>=', $on)
            ->first();

        if ($balance !== null) {
            $this->leaveBalanceNotice($balance, expiring: false);
        }
    }

    public function warnAdjustedBalance(LeaveBalance $balance): void
    {
        $this->leaveBalanceNotice($balance, expiring: false);
    }

    /**
     * One logical notice per recipient, dedupe key, and channel. A sent claim
     * is not sent again. A claim with no status was stored before the column
     * existed and is not sent again. A queued claim left by a crashed worker
     * can be taken again. SMS also stores the same key on sms_messages. Mail
     * has no provider idempotency key, so a timeout after acceptance can
     * still deliver twice.
     */
    public function deliver(
        User $user,
        string $event,
        string $title,
        string $body,
        string $dedupeKey,
        ?string $actionUrl = null,
        ?string $actionLabel = null,
    ): void {
        $user->loadMissing('organization');
        $organization = $user->organization;

        if ($organization === null || (int) $user->organization_id !== (int) $organization->getKey()) {
            return;
        }

        $channels = NotificationPreference::channelsFor($organization, $event, $user);

        if (($channels['database'] || $channels['mail']) && $this->claim($user, $organization, $dedupeKey, 'notice')) {
            try {
                $user->notify(new WorkforceMessage($organization, $event, $title, $body, $actionUrl, $actionLabel));
                $this->mark($user, $dedupeKey, 'notice', 'sent');
            } catch (Throwable $exception) {
                $this->release($user, $dedupeKey, 'notice');
                throw $exception;
            }
        }

        if ($channels['push'] && $this->claim($user, $organization, $dedupeKey, 'push')) {
            try {
                $this->push->send($user, $title, $body);
                $this->mark($user, $dedupeKey, 'push', 'sent');
            } catch (Throwable $exception) {
                $this->release($user, $dedupeKey, 'push');
                throw $exception;
            }
        }

        if (! $channels['sms'] || blank($user->phone)) {
            return;
        }

        if (! SmsProvider::query()->where('is_active', true)->where('enabled', true)->exists()) {
            return;
        }

        if (! $this->claim($user, $organization, $dedupeKey, 'sms')) {
            return;
        }

        try {
            app(SmsTransmitter::class)->transmit(
                $user->phone,
                mb_substr($body, 0, 1000),
                $dedupeKey,
                'workforce_'.$event,
                $organization->getKey(),
            );
            $this->mark($user, $dedupeKey, 'sms', 'sent');
        } catch (Throwable $exception) {
            if (DeliveryFailure::shouldRetry($exception)) {
                $this->release($user, $dedupeKey, 'sms');
                throw $exception;
            }

            $this->mark($user, $dedupeKey, 'sms', 'failed', DeliveryFailure::kind($exception));
        }
    }

    /**
     * @param  array<string, scalar|null>  $vars
     */
    private function send(
        User $user,
        string $event,
        string $title,
        string $body,
        ?string $actionUrl = null,
        ?string $actionLabel = null,
        ?string $dedupe = null,
        array $vars = [],
    ): void {
        $user->loadMissing('organization');
        $organization = $user->organization;

        if ($organization === null || (int) $user->organization_id !== (int) $organization->getKey()) {
            return;
        }

        $vars = array_merge([
            'person_name' => (string) $user->first_name,
            'organization_name' => (string) $organization->name,
            'action_url' => (string) ($actionUrl ?? ''),
        ], $vars);

        [$title, $body] = NotificationCopy::apply(
            $this->templateFor($organization, $event),
            $title,
            $body,
            $vars,
        );

        DeliverWorkforceNotice::dispatch(
            (int) $organization->getKey(),
            (int) $user->getKey(),
            $event,
            $title,
            $body,
            mb_substr($dedupe ?: ('workforce-'.$event.'-'.$user->getKey()), 0, 80),
            $actionUrl,
            $actionLabel,
        )->afterCommit();
    }

    private function wants(User $user, string $event): bool
    {
        $user->loadMissing('organization');
        $organization = $user->organization;

        if ($organization === null) {
            return false;
        }

        $channels = NotificationPreference::channelsFor($organization, $event, $user);

        return $channels['mail'] || $channels['database'] || $channels['sms'] || $channels['push'];
    }

    private function tellApprovers(LeaveRequest $request, string $event, string $title, string $body, string $dedupe): void
    {
        $request->loadMissing('organization', 'staffMember.department', 'policy', 'leaveType');
        $organization = $request->organization;

        if ($organization === null) {
            return;
        }

        $url = Origin::url($organization, '/org/leave');
        $vars = $this->leaveVars($request);

        $this->approvers($request)->orderBy('id')->chunkById(100, function ($users) use ($event, $title, $body, $url, $dedupe, $vars): void {
            foreach ($users as $user) {
                $this->send($user, $event, $title, $body, $url, 'Review leave', $dedupe.'-'.$user->getKey(), $vars);
            }
        });
    }

    /**
     * @return Builder<User>
     */
    private function approvers(LeaveRequest $request): Builder
    {
        $query = User::query()
            ->where('organization_id', $request->organization_id)
            ->where('id', '!=', $request->staffMember?->user_id ?? 0);

        $mode = $request->policy?->approval_mode ?? LeaveApprovalMode::AnyApprover;
        $staff = $request->staffMember;
        $specific = null;

        if ($mode === LeaveApprovalMode::LineManager && $staff?->reports_to_id) {
            $specific = StaffMember::query()->whereKey($staff->reports_to_id)->value('user_id');
        }

        if ($specific === null && $mode === LeaveApprovalMode::DepartmentHead) {
            $headId = $staff?->department?->head_staff_member_id;
            $specific = $headId ? StaffMember::query()->whereKey($headId)->value('user_id') : null;
        }

        if ($specific) {
            $query->whereKey($specific);
        } else {
            $query->permission('leave.approve');
        }

        return $query;
    }

    private function leaveSummary(LeaveRequest $request, string $verb): string
    {
        $request->loadMissing('staffMember', 'leaveType');
        $name = $request->staffMember?->name ?? 'A colleague';
        $type = $request->leaveType?->name ?? 'leave';

        return $name.' '.$verb.' '.$type.' from '.$request->starts_on->format('j F Y').' to '.$request->ends_on->format('j F Y').'.';
    }

    private function leaveOutcome(LeaveRequest $request, string $outcome): string
    {
        $request->loadMissing('leaveType');
        $type = $request->leaveType?->name ?? 'Leave';

        return 'Your '.$type.' request from '.$request->starts_on->format('j F Y').' to '.$request->ends_on->format('j F Y').' '.$outcome.'.';
    }

    /**
     * @return array<string, string>
     */
    private function leaveVars(LeaveRequest $request): array
    {
        $request->loadMissing('leaveType', 'staffMember');

        return [
            'leave_type' => (string) ($request->leaveType?->name ?? 'Leave'),
            'start_date' => $request->starts_on->format('j F Y'),
            'end_date' => $request->ends_on->format('j F Y'),
            'person_name' => (string) ($request->staffMember?->name ?? ''),
        ];
    }

    private function leaveBalanceNotice(LeaveBalance $balance, bool $expiring): bool
    {
        $available = $balance->available();

        if ($expiring && $available < 1) {
            return false;
        }

        if (! $expiring && $available > self::LOW_BALANCE_AT) {
            return false;
        }

        $balance->loadMissing('staffMember.user', 'leaveType', 'organization');
        $user = $balance->staffMember?->user;
        $organization = $balance->organization;

        if ($user === null || $organization === null || (int) $user->organization_id !== (int) $organization->getKey()) {
            return false;
        }

        $type = $balance->leaveType?->name ?? 'Leave';
        $days = $available.' '.($available === 1 ? 'day' : 'days');

        if ($expiring) {
            $end = $balance->period_end->format('j F Y');
            $this->send(
                $user,
                'leave.balance_expiring',
                'Leave balance expiring',
                'Your '.$type.' balance runs until '.$end.'. '.$days.' remain.',
                Origin::url($organization, '/org/leave'),
                'View leave',
                'leave-expiring-'.$balance->getKey().'-'.$balance->period_end->toDateString(),
                ['leave_type' => $type, 'end_date' => $end],
            );

            return true;
        }

        $this->send(
            $user,
            'leave.balance_warning',
            'Leave balance is low',
            'You have '.$days.' of '.$type.' remaining in this leave year.',
            Origin::url($organization, '/org/leave'),
            'View leave',
            'leave-balance-'.$balance->getKey().'-'.$available,
            ['leave_type' => $type],
        );

        return true;
    }

    private function templateFor(Organization $organization, string $event): ?NotificationTemplate
    {
        $organizationId = (int) $organization->getKey();

        if (! array_key_exists($organizationId, $this->templates)) {
            $this->templates[$organizationId] = NotificationTemplate::withoutTenantScope()
                ->where('organization_id', $organizationId)
                ->where('channel', 'all')
                ->get()
                ->keyBy('event');
        }

        return $this->templates[$organizationId]->get($event);
    }

    private function claim(User $user, Organization $organization, string $dedupeKey, string $channel): bool
    {
        $existing = WorkforceNoticeDelivery::query()
            ->where('user_id', $user->getKey())
            ->where('dedupe_key', $dedupeKey)
            ->where('channel', $channel)
            ->first();

        if ($existing !== null) {
            // Null is a claim stored before status existed. It already finished.
            // orbit:data:backfill-notice-claims records it as sent. It is not queued.
            if ($existing->status === null || $existing->status === 'sent' || ($existing->status === 'failed' && in_array($existing->failure_kind, ['permanent', 'invalid_configuration'], true))) {
                return false;
            }

            $existing->update([
                'status' => 'queued',
                'failure_kind' => null,
                'attempts' => (int) $existing->attempts + 1,
            ]);

            return true;
        }

        try {
            WorkforceNoticeDelivery::query()->create([
                'organization_id' => $organization->getKey(),
                'user_id' => $user->getKey(),
                'dedupe_key' => $dedupeKey,
                'channel' => $channel,
                'status' => 'queued',
                'attempts' => 1,
            ]);

            return true;
        } catch (UniqueConstraintViolationException) {
            return false;
        }
    }

    private function mark(User $user, string $dedupeKey, string $channel, string $status, ?string $failureKind = null): void
    {
        WorkforceNoticeDelivery::query()
            ->where('user_id', $user->getKey())
            ->where('dedupe_key', $dedupeKey)
            ->where('channel', $channel)
            ->update([
                'status' => $status,
                'failure_kind' => $failureKind,
            ]);
    }

    private function release(User $user, string $dedupeKey, string $channel): void
    {
        WorkforceNoticeDelivery::query()
            ->where('user_id', $user->getKey())
            ->where('dedupe_key', $dedupeKey)
            ->where('channel', $channel)
            ->delete();
    }
}
