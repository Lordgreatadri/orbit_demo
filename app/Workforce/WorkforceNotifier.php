<?php

namespace App\Workforce;

use App\Contracts\PushNotifier;
use App\Enums\AttendanceStatus;
use App\Enums\FieldActivityStatus;
use App\Enums\ShiftStatus;
use App\Enums\UserStatus;
use App\Integrations\Sms\SmsTransmitter;
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
use App\Models\WorkforcePolicy;
use App\Notifications\WorkforceMessage;
use App\Tenancy\Origin;
use Illuminate\Support\Str;

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
            ->chunkById(100, function ($users) use ($title, $body, $url): void {
                foreach ($users as $user) {
                    $this->send($user, 'session.opened', $title, $body, $url, 'Open the check-in code');
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
        );
    }

    public function fieldAssigned(FieldAssignment $assignment): void
    {
        $assignment->loadMissing('staffMember.user', 'organization');
        $user = $assignment->staffMember?->user;

        if ($user === null) {
            return;
        }

        $this->send($user, 'field.assigned', 'Field assignment', $assignment->title.' from '.$assignment->starts_on->toFormattedDateString().'.');
    }

    public function leaveSubmitted(LeaveRequest $request): void
    {
        $request->loadMissing('organization', 'staffMember');
        User::query()
            ->where('organization_id', $request->organization_id)
            ->permission('leave.approve')
            ->get()
            ->each(fn (User $user) => $this->send(
                $user,
                'leave.submitted',
                'Leave request submitted',
                $request->staffMember->name.' requested leave from '.$request->starts_on->toDateString().' to '.$request->ends_on->toDateString().'.',
            ));
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
        );
    }

    public function remindDueShifts(): int
    {
        $sent = 0;

        Organization::query()->orderBy('id')->each(function (Organization $organization) use (&$sent): void {
            $tomorrow = now($organization->timezone ?: 'UTC')->addDay()->toDateString();

            ShiftAssignment::withoutTenantScope()
                ->where('organization_id', $organization->getKey())
                ->whereDate('work_date', $tomorrow)
                ->whereHas('shift', fn ($query) => $query->where('status', ShiftStatus::Published->value))
                ->with(['staffMember.user', 'shift'])
                ->orderBy('id')
                ->each(function (ShiftAssignment $assignment) use (&$sent): void {
                    $user = $assignment->staffMember?->user;

                    if ($user === null || $assignment->shift === null) {
                        return;
                    }

                    $this->send(
                        $user,
                        'schedule.reminder',
                        'Upcoming schedule',
                        $assignment->shift->name.' is on your schedule for '.$assignment->work_date->toFormattedDateString().'.',
                    );
                    $sent++;
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
        );
    }

    private function send(User $user, string $event, string $title, string $body, ?string $actionUrl = null, ?string $actionLabel = null): void
    {
        $user->loadMissing('organization');
        $organization = $user->organization;

        if ($organization === null) {
            return;
        }

        $user->notify(new WorkforceMessage($organization, $event, $title, $body, $actionUrl, $actionLabel));

        $channels = NotificationPreference::channelsFor($organization, $event);

        if ($channels['push']) {
            $this->push->send($user, $title, $body);
        }

        if ($channels['sms'] && filled($user->phone) && SmsProvider::query()->where('is_active', true)->where('enabled', true)->exists()) {
            app(SmsTransmitter::class)->transmit(
                $user->phone,
                $body,
                'workforce-'.Str::uuid(),
                'workforce_'.$event,
                $organization->getKey(),
            );
        }
    }
}
