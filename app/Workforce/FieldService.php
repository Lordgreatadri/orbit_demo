<?php

namespace App\Workforce;

use App\Enums\FieldActivityStatus;
use App\Enums\FieldAssignmentStatus;
use App\Enums\VerificationMethod;
use App\Models\FieldActivity;
use App\Models\FieldAssignment;
use App\Models\Location;
use App\Models\StaffMember;
use App\Models\User;
use App\Models\WorkforcePolicy;
use App\Support\Audit;
use Illuminate\Support\Carbon;

final class FieldService
{
    public function __construct(private readonly AttendanceEngine $attendance) {}

    public function assign(User $actor, StaffMember $staff, Location $location, string $title, ?string $instructions, Carbon $starts, Carbon $ends): FieldAssignment
    {
        $assignment = FieldAssignment::query()->create([
            'organization_id' => $staff->organization_id,
            'staff_member_id' => $staff->getKey(),
            'location_id' => $location->getKey(),
            'assigned_by' => $actor->getKey(),
            'title' => $title,
            'instructions' => $instructions,
            'starts_on' => $starts->toDateString(),
            'ends_on' => $ends->toDateString(),
            'status' => FieldAssignmentStatus::Assigned,
        ]);

        Audit::log('field.assigned', $assignment, ['title' => $title], $actor);
        app(WorkforceNotifier::class)->fieldAssigned($assignment);

        return $assignment;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function start(User $actor, FieldAssignment $assignment, array $payload): FieldActivity
    {
        $assignment->loadMissing(['staffMember.organization', 'location']);
        $staff = $assignment->staffMember;
        $today = now($staff->organization->timezone ?: 'UTC')->toDateString();

        if ($assignment->starts_on->toDateString() > $today || $assignment->ends_on->toDateString() < $today) {
            throw new AttendanceException('assignment_closed', 'This field assignment is not scheduled for today.');
        }

        if ($assignment->activities()->whereNull('ended_at')->exists()) {
            throw new AttendanceException('duplicate', 'This assignment already has an open activity.');
        }

        $payload['methods'] = ['gps'];
        $payload['location_uuid'] = $assignment->location->uuid;

        $this->attendance->checkIn($actor, $staff, $payload);

        $activity = FieldActivity::query()->create([
            'organization_id' => $assignment->organization_id,
            'field_assignment_id' => $assignment->getKey(),
            'staff_member_id' => $staff->getKey(),
            'started_at' => now(),
            'status' => FieldActivityStatus::Active,
            'last_latitude' => isset($payload['latitude']) ? round((float) $payload['latitude'], 5) : null,
            'last_longitude' => isset($payload['longitude']) ? round((float) $payload['longitude'], 5) : null,
            'last_accuracy_m' => isset($payload['accuracy_m']) ? (int) round((float) $payload['accuracy_m']) : null,
            'last_reported_at' => now(),
        ]);

        $assignment->update(['status' => FieldAssignmentStatus::Active]);
        Audit::log('field.started', $activity, ['assignment' => $assignment->uuid], $actor);

        return $activity;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function finish(User $actor, FieldActivity $activity, array $payload): void
    {
        $activity->loadMissing('assignment.location', 'staffMember.organization');
        $staff = $activity->staffMember;

        if ($activity->ended_at !== null) {
            throw new AttendanceException('duplicate', 'This field activity is already closed.');
        }

        $payload['methods'] = ['gps'];
        $payload['location_uuid'] = $activity->assignment->location->uuid;
        $bundle = app(VerificationCentre::class)->assess($actor, $staff, WorkforcePolicy::resolve($staff->organization), $payload);
        $gps = $bundle->forMethod(VerificationMethod::Gps);

        if (! $gps?->accepted) {
            throw new AttendanceException($gps->reason ?? 'location_unavailable', $this->attendance->message($gps->reason ?? 'location_unavailable'));
        }

        try {
            $this->attendance->checkOut($actor, $staff);
        } catch (AttendanceException $exception) {
            if (! in_array($exception->reason, ['not_checked_in', 'duplicate'], true)) {
                throw $exception;
            }
        }

        $activity->update([
            'ended_at' => now(),
            'status' => FieldActivityStatus::Completed,
            'last_reported_at' => now(),
        ]);
        $activity->assignment->update(['status' => FieldAssignmentStatus::Completed]);
        Audit::log('field.completed', $activity, [], $actor);
    }
}
