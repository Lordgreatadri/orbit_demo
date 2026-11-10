<?php

namespace App\Workforce;

use App\Contracts\PushNotifier;
use App\Enums\AttendanceStatus;
use App\Enums\FieldActivityStatus;
use App\Enums\LeaveApprovalMode;
use App\Enums\ShiftStatus;
use App\Enums\UserStatus;
use App\Integrations\Sms\SmsTransmitter;
use App\Jobs\Workforce\DeliverWorkforceNotice;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\FieldActivity;
use App\Models\FieldAssignment;
use App\Models\LeaveRequest;
use App\Models\NotificationPreference;
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
use Illuminate\Database\UniqueConstraintViolationException;

final class WorkforceNotifier
{
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
            dedupe: 'attendance-'.$record->getKey().'-'.($late ? 'late' : 'confirmed'),
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
            ->chunkById(100, function ($users) use ($session, $title, $body, $url): void {
                foreach ($users as $user) {
                    $this->send($user, 'session.opened', $title, $body, $url, 'Open the check-in code', 'session-opened-'.$session->getKey().'-'.$user->getKey());
                }
            });
    }

    public function schedulePublished(ShiftAssignment $assignment): void
    {
        $assignment->loadMissing('staffMember.user', 'shift', 'organization');
        $user = $assignment->staffMember?->user;

        if ($user === null) {
            return;
        }

        $this->send(
            $user,
            'schedule.published',
            'Schedule published',
            $assignment->shift->name.' is on your schedule for '.$assignment->work_date->toFormattedDateString().'.',
            dedupe: 'schedule-published-'.$assignment->getKey(),
        );
    }

    public function scheduleRemoved(ShiftAssignment $assignment): void
    {
        $assignment->loadMissing('staffMember.user', 'shift', 'organization');
        $user = $assignment->staffMember?->user;

        if ($user === null || $assignment->shift === null) {
            return;
        }

        $this->send(
            $user,
            'schedule.published',
            'Schedule changed',
            $assignment->shift->name.' was removed from your schedule for '.$assignment->work_date?->toFormattedDateString().'.',
            dedupe: 'schedule-removed-'.$assignment->getKey(),
        );
    }

    public function fieldAssigned(FieldAssignment $assignment): void
    {
        $assignment->loadMissing('staffMember.user', 'organization');
        $user = $assignment->staffMember?->user;

        if ($user === null) {
            return;
        }

        $this->send($user, 'field.assigned', 'Field assignment', $assignment->title.' from '.$assignment->starts_on->toFormattedDateString().'.', dedupe: 'field-assigned-'.$assignment->getKey());
    }

    public function leaveSubmitted(LeaveRequest $request): void
    {
        $request->loadMissing('organization', 'staffMember.department', 'policy');
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

        $query->orderBy('id')->chunkById(100, function ($users) use ($request): void {
            foreach ($users as $user) {
                $this->send(
                    $user,
                    'leave.submitted',
                    'Leave request submitted',
                    $request->staffMember->name.' requested leave from '.$request->starts_on->toDateString().' to '.$request->ends_on->toDateString().'.',
                    dedupe: 'leave-submitted-'.$request->getKey().'-'.$user->getKey(),
                );
            }
        });
    }

    public function missingCheckout(AttendanceRecord $record): void
    {
        $record->loadMissing('organization');
        $user = $record->user_id ? User::query()->find($record->user_id) : null;
        $user ??= StaffMember::withoutTenantScope()->find($record->staff_member_id)?->user;

        if ($user === null) {
            return;
        }

        $this->send(
            $user,
            'attendance.missing_checkout',
            'Missing check-out',
            'An open attendance record was marked incomplete because check-out was not recorded.',
            dedupe: 'attendance-missing-'.$record->getKey(),
        );
    }

    public function remindDueShifts(): int
    {
        $sent = 0;

        Organization::query()->orderBy('id')->each(function (Organization $organization) use (&$sent): void {
            $tomorrow = now($organization->timezone ?: 'UTC')->addDay()->toDateString();

            CalendarDay::constrain(
                ShiftAssignment::withoutTenantScope()->where('organization_id', $organization->getKey()),
                'work_date',
                $tomorrow,
            )
                ->whereNull('reminded_at')
                ->whereHas('shift', fn ($query) => $query->where('status', ShiftStatus::Published->value))
                ->with(['staffMember.user', 'shift'])
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

                        if ($claimed !== 1) {
                            continue;
                        }

                        $this->send(
                            $user,
                            'schedule.reminder',
                            'Upcoming schedule',
                            $assignment->shift->name.' is on your schedule for '.$assignment->work_date->toFormattedDateString().'.',
                            dedupe: 'schedule-reminder-'.$assignment->getKey(),
                        );
                        $sent++;
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
                        dedupe: 'field-alert-'.$activity->getKey().'-'.now()->toDateString(),
                    );
                }

                $flagged++;
            });

        return $flagged;
    }

    public function leaveDecided(LeaveRequest $request): void
    {
        $request->loadMissing('staffMember.user', 'organization');
        $user = $request->staffMember?->user;

        if ($user === null) {
            return;
        }

        $this->send(
            $user,
            'leave.decided',
            'Leave '.$request->status->label(),
            'Your leave request was '.$request->status->label().($request->decision_note ? ': '.$request->decision_note : '.'),
            dedupe: 'leave-decided-'.$request->getKey().'-'.$request->status->value,
        );
    }

    private function send(
        User $user,
        string $event,
        string $title,
        string $body,
        ?string $actionUrl = null,
        ?string $actionLabel = null,
        ?string $dedupe = null,
    ): void {
        $user->loadMissing('organization');
        $organization = $user->organization;

        if ($organization === null) {
            return;
        }

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

    /**
     * One logical notice per recipient, dedupe key, and channel. A completed
     * claim is not sent again. SMS also stores that key on sms_messages.
     * Mail and push have no provider idempotency key, so a provider that
     * accepted the message and then timed out can still deliver it twice.
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

        if ($organization === null) {
            return;
        }

        $channels = NotificationPreference::channelsFor($organization, $event);

        if ($this->claim($user, $organization, $dedupeKey, 'notice')) {
            try {
                $user->notify(new WorkforceMessage($organization, $event, $title, $body, $actionUrl, $actionLabel));
            } catch (\Throwable $exception) {
                $this->release($user, $dedupeKey, 'notice');
                throw $exception;
            }
        }

        if ($channels['push'] && $this->claim($user, $organization, $dedupeKey, 'push')) {
            try {
                $this->push->send($user, $title, $body);
            } catch (\Throwable $exception) {
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

        try {
            app(SmsTransmitter::class)->transmit(
                $user->phone,
                $body,
                $dedupeKey,
                'workforce_'.$event,
                $organization->getKey(),
            );
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    private function claim(User $user, Organization $organization, string $dedupeKey, string $channel): bool
    {
        try {
            WorkforceNoticeDelivery::query()->create([
                'organization_id' => $organization->getKey(),
                'user_id' => $user->getKey(),
                'dedupe_key' => $dedupeKey,
                'channel' => $channel,
            ]);

            return true;
        } catch (UniqueConstraintViolationException) {
            return false;
        }
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
