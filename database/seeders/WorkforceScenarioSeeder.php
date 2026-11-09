<?php

namespace Database\Seeders;

use App\Enums\FieldAssignmentStatus;
use App\Enums\LeaveStatus;
use App\Enums\LocationType;
use App\Enums\ShiftStatus;
use App\Enums\StaffStatus;
use App\Enums\WorkType;
use App\Models\Beacon;
use App\Models\Department;
use App\Models\FieldAssignment;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Location;
use App\Models\NotificationPreference;
use App\Models\Organization;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\StaffMember;
use App\Models\User;
use App\Models\WifiNetwork;
use App\Models\WorkforcePolicy;
use App\Workforce\WorkforceEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Idempotent workforce defaults for fictional demo organisations.
 *
 * Materialises the directory already stored on each demo organisation into
 * departments, locations, and staff rows, then backfills the workforce tables
 * migrations leave empty: notification channels, a beacon and a fictional
 * Wi-Fi address per site, a day and night (or lesson) pattern, one field
 * assignment, and one pending leave request. It does not insert attendance
 * counts or change organisation settings. Coordinates and BSSIDs are plot
 * values for tests, not surveyed sites.
 */
class WorkforceScenarioSeeder extends Seeder
{
    public function run(): void
    {
        Organization::query()->orderBy('id')->each(function (Organization $organization): void {
            if (! $organization->isDemo()) {
                return;
            }

            WorkforcePolicy::resolve($organization);
            $this->leaveTypes($organization);

            $settings = $organization->settings ?? [];
            $departments = $this->departments($organization, $settings['departments'] ?? ['Administration']);
            $locations = $this->locations($organization, $settings['locations'] ?? [[
                'name' => 'Head office',
                'kind' => 'office',
                'city' => $organization->city,
            ]]);
            $directory = $settings['sample_directory'] ?? $this->fallbackDirectory($organization, $departments, $locations);

            foreach ($directory as $email => $row) {
                $this->staff($organization, (string) $email, $row, $departments, $locations);
            }

            $this->scenarios($organization);
        });
    }

    private function leaveTypes(Organization $organization): void
    {
        $types = [
            ['code' => 'ANNUAL', 'name' => 'Annual', 'paid' => true],
            ['code' => 'SICK', 'name' => 'Sick', 'paid' => true],
        ];

        if ($organization->isEducational()) {
            $types[] = ['code' => 'STUDY', 'name' => 'Study', 'paid' => false];
        }

        foreach ($types as $type) {
            LeaveType::withoutTenantScope()->firstOrCreate(
                [
                    'organization_id' => $organization->getKey(),
                    'code' => $type['code'],
                ],
                [
                    'name' => $type['name'],
                    'paid' => $type['paid'],
                    'requires_balance' => false,
                    'is_active' => true,
                ],
            );
        }
    }

    /**
     * @param  list<string>  $names
     * @return array<string, Department>
     */
    private function departments(Organization $organization, array $names): array
    {
        $created = [];

        foreach (array_values($names) as $index => $name) {
            $created[$name] = Department::withoutTenantScope()->firstOrCreate(
                [
                    'organization_id' => $organization->getKey(),
                    'name' => $name,
                ],
                [
                    'code' => strtoupper(Str::substr(Str::slug($name, ''), 0, 8)).($index + 1),
                    'is_active' => true,
                ],
            );
        }

        return $created;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, Location>
     */
    private function locations(Organization $organization, array $rows): array
    {
        $created = [];

        foreach (array_values($rows) as $index => $row) {
            $name = (string) ($row['name'] ?? 'Site '.($index + 1));
            $kind = (string) ($row['kind'] ?? 'office');
            $type = match ($kind) {
                'field' => LocationType::FieldArea,
                'remote' => LocationType::Remote,
                'campus' => LocationType::Office,
                default => LocationType::Office,
            };

            $created[$name] = Location::withoutTenantScope()->firstOrCreate(
                [
                    'organization_id' => $organization->getKey(),
                    'name' => $name,
                ],
                [
                    'type' => $type,
                    'city' => $row['city'] ?? $organization->city,
                    'latitude' => 5.55 + ($index * 0.02),
                    'longitude' => -0.20 - ($index * 0.02),
                    'geofence_radius_m' => $type === LocationType::FieldArea ? 500 : 200,
                    'is_active' => true,
                ],
            );
        }

        return $created;
    }

    /**
     * @param  array<string, Department>  $departments
     * @param  array<string, Location>  $locations
     * @return array<string, array<string, string>>
     */
    private function fallbackDirectory(Organization $organization, array $departments, array $locations): array
    {
        $department = array_key_first($departments) ?: 'Administration';
        $location = array_key_first($locations) ?: 'Head office';
        $prefix = strtoupper(Str::substr(Str::slug($organization->slug, ''), 0, 4));
        $directory = [];

        User::query()
            ->where('organization_id', $organization->getKey())
            ->orderBy('id')
            ->each(function (User $user) use (&$directory, $prefix, $department, $location): void {
                $directory[$user->email] = [
                    'member_id' => $prefix.'-DEMO-'.$user->getKey(),
                    'department' => $department,
                    'location' => $location,
                    'work_type' => 'office',
                ];
            });

        return $directory;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, Department>  $departments
     * @param  array<string, Location>  $locations
     */
    private function staff(Organization $organization, string $email, array $row, array $departments, array $locations): void
    {
        $user = User::query()->where('email', $email)->where('organization_id', $organization->getKey())->first();
        $number = (string) ($row['member_id'] ?? strtoupper(Str::substr(md5($email), 0, 8)));
        $workType = match ((string) ($row['work_type'] ?? 'office')) {
            'field' => WorkType::Field,
            'remote' => WorkType::Remote,
            'hybrid' => WorkType::Hybrid,
            'student', 'lecturer' => WorkType::Office,
            default => WorkType::Office,
        };

        $staff = StaffMember::withoutTenantScope()->firstOrCreate(
            [
                'organization_id' => $organization->getKey(),
                'staff_number' => $number,
            ],
            [
                'user_id' => $user && StaffMember::withoutTenantScope()->where('user_id', $user->getKey())->doesntExist()
                    ? $user->getKey()
                    : null,
                'department_id' => ($departments[$row['department'] ?? ''] ?? null)?->getKey(),
                'primary_location_id' => ($locations[$row['location'] ?? ''] ?? null)?->getKey(),
                'first_name' => $user?->first_name ?? 'Demo',
                'last_name' => $user?->last_name ?? 'Person',
                'email' => $email,
                'phone' => $user?->phone,
                'job_title' => match ((string) ($row['work_type'] ?? '')) {
                    'student' => 'Student',
                    'lecturer' => 'Lecturer',
                    'field' => 'Field officer',
                    default => 'Staff',
                },
                'work_type' => $workType,
                'status' => StaffStatus::Active,
                'started_on' => '2026-01-06',
                'notes' => 'Fictional demo register row. Not a live personnel record.',
            ],
        );

        if ($staff->user_id === null && $user && StaffMember::withoutTenantScope()->where('user_id', $user->getKey())->doesntExist()) {
            $staff->forceFill(['user_id' => $user->getKey()])->save();
        }
    }

    /**
     * Idempotent rows for tables that migrations create empty.
     */
    private function scenarios(Organization $organization): void
    {
        foreach (WorkforceEvents::keys() as $event) {
            NotificationPreference::withoutTenantScope()->firstOrCreate(
                ['organization_id' => $organization->getKey(), 'event' => $event],
                ['mail' => true, 'database' => true, 'sms' => false, 'push' => false],
            );
        }

        $locations = Location::withoutTenantScope()
            ->where('organization_id', $organization->getKey())
            ->orderBy('id')
            ->get();

        foreach ($locations as $location) {
            $hex = strtoupper(substr(md5((string) $location->uuid), 0, 12));

            Beacon::withoutTenantScope()->firstOrCreate(
                [
                    'organization_id' => $organization->getKey(),
                    'public_id' => 'site-'.substr($hex, 0, 8),
                ],
                [
                    'location_id' => $location->getKey(),
                    'name' => $location->name.' beacon',
                    'is_active' => true,
                ],
            );

            WifiNetwork::withoutTenantScope()->firstOrCreate(
                [
                    'organization_id' => $organization->getKey(),
                    'bssid' => implode(':', str_split($hex, 2)),
                ],
                [
                    'location_id' => $location->getKey(),
                    'label' => $location->name.' network',
                    'is_active' => true,
                ],
            );
        }

        $patterns = $organization->isEducational()
            ? [
                ['Morning lesson', '08:00:00', '10:00:00', false],
                ['Evening lesson', '18:00:00', '21:00:00', false],
            ]
            : [
                ['Day shift', '08:00:00', '17:00:00', false],
                ['Night shift', '22:00:00', '06:00:00', true],
            ];

        $timezone = $organization->timezone ?: 'UTC';
        $shifts = [];

        foreach ($patterns as [$name, $start, $end, $midnight]) {
            $shifts[] = Shift::withoutTenantScope()->firstOrCreate(
                ['organization_id' => $organization->getKey(), 'name' => $name],
                [
                    'starts_at' => $start,
                    'ends_at' => $end,
                    'crosses_midnight' => $midnight,
                    'effective_from' => now($timezone)->startOfMonth()->toDateString(),
                    'effective_until' => now($timezone)->addMonths(3)->endOfMonth()->toDateString(),
                    'status' => ShiftStatus::Published,
                ],
            );
        }

        $people = StaffMember::withoutTenantScope()
            ->where('organization_id', $organization->getKey())
            ->orderBy('id')
            ->get();
        $office = $people->first(fn (StaffMember $person) => $person->work_type !== WorkType::Field) ?? $people->first();
        $second = $people->first(fn (StaffMember $person) => $office !== null && $person->isNot($office));

        if ($office !== null && isset($shifts[0])) {
            ShiftAssignment::withoutTenantScope()->firstOrCreate(
                [
                    'organization_id' => $organization->getKey(),
                    'shift_id' => $shifts[0]->getKey(),
                    'staff_member_id' => $office->getKey(),
                ],
                ['work_date' => now($timezone)->toDateString()],
            );
        }

        if ($second !== null && isset($shifts[1])) {
            ShiftAssignment::withoutTenantScope()->firstOrCreate(
                [
                    'organization_id' => $organization->getKey(),
                    'shift_id' => $shifts[1]->getKey(),
                    'staff_member_id' => $second->getKey(),
                ],
                ['work_date' => now($timezone)->addDay()->toDateString()],
            );
        }

        $field = $people->first(fn (StaffMember $person) => $person->work_type === WorkType::Field);
        $area = $locations->first(fn (Location $location) => $location->type === LocationType::FieldArea) ?? $locations->first();

        if ($field !== null && $area !== null) {
            FieldAssignment::withoutTenantScope()->firstOrCreate(
                [
                    'organization_id' => $organization->getKey(),
                    'staff_member_id' => $field->getKey(),
                    'title' => 'Routine site inspection',
                ],
                [
                    'location_id' => $area->getKey(),
                    'instructions' => 'Fictional demo assignment. Capture location only when starting or finishing.',
                    'starts_on' => now($timezone)->toDateString(),
                    'ends_on' => now($timezone)->addDays(14)->toDateString(),
                    'status' => FieldAssignmentStatus::Assigned,
                ],
            );
        }

        $annual = LeaveType::withoutTenantScope()
            ->where('organization_id', $organization->getKey())
            ->where('code', 'ANNUAL')
            ->first();

        if ($annual !== null && $office !== null) {
            LeaveRequest::withoutTenantScope()->firstOrCreate(
                [
                    'organization_id' => $organization->getKey(),
                    'staff_member_id' => $office->getKey(),
                    'starts_on' => '2026-12-01',
                ],
                [
                    'leave_type_id' => $annual->getKey(),
                    'ends_on' => '2026-12-02',
                    'reason' => 'Fictional pending request for demonstration. It is not approved.',
                    'status' => LeaveStatus::Submitted,
                ],
            );
        }
    }
}
