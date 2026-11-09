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
use App\Models\LeaveEntitlementRule;
use App\Models\LeavePolicy;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Location;
use App\Models\NotificationPreference;
use App\Models\Organization;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\StaffCategory;
use App\Models\StaffMember;
use App\Models\User;
use App\Models\WifiNetwork;
use App\Models\WorkArrangement;
use App\Models\WorkforcePolicy;
use App\Workforce\LeavePlanner;
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
            app(LeavePlanner::class)->ensureArrangements($organization);
            $categories = $this->categories($organization);

            $settings = $organization->settings ?? [];
            $departments = $this->departments($organization, $settings['departments'] ?? ['Administration']);
            $locations = $this->locations($organization, $settings['locations'] ?? [[
                'name' => 'Head office',
                'kind' => 'office',
                'city' => $organization->city,
            ]]);
            $directory = $settings['sample_directory'] ?? $this->fallbackDirectory($organization, $departments, $locations);

            foreach ($directory as $email => $row) {
                $this->staff($organization, (string) $email, $row, $departments, $locations, $categories);
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
     * @return array<string, StaffCategory>
     */
    private function categories(Organization $organization): array
    {
        $rows = $organization->isEducational()
            ? [
                ['TEACH', 'Teaching'],
                ['ADMIN', 'Administrative'],
                ['SUPPORT', 'Support'],
            ]
            : [
                ['MANAGER', 'Manager'],
                ['FINANCE', 'Finance'],
                ['JUNIOR', 'Junior staff'],
                ['FIELD', 'Field'],
            ];

        $created = [];

        foreach ($rows as [$code, $name]) {
            $created[$code] = StaffCategory::withoutTenantScope()->firstOrCreate(
                ['organization_id' => $organization->getKey(), 'code' => $code],
                ['name' => $name],
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
     * @param  array<string, StaffCategory>  $categories
     */
    private function staff(Organization $organization, string $email, array $row, array $departments, array $locations, array $categories = []): void
    {
        $user = User::query()->where('email', $email)->where('organization_id', $organization->getKey())->first();
        [$firstName, $lastName] = $this->directoryNames($user, $email, $row);
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
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'phone' => $user?->phone,
                'job_title' => match ((string) ($row['work_type'] ?? '')) {
                    'student' => 'Student',
                    'lecturer' => 'Lecturer',
                    'field' => 'Field officer',
                    default => 'Staff',
                },
                'work_type' => $workType,
                'grade' => $row['grade'] ?? null,
                'employment_type' => $row['employment_type'] ?? null,
                'staff_category_id' => ($categories[$row['category'] ?? ''] ?? null)?->getKey(),
                'status' => StaffStatus::Active,
                'started_on' => $row['started_on'] ?? '2026-01-06',
                'notes' => 'Fictional demo register row. Not a live personnel record.',
            ],
        );

        if ($staff->user_id === null && $user && StaffMember::withoutTenantScope()->where('user_id', $user->getKey())->doesntExist()) {
            $staff->forceFill(['user_id' => $user->getKey()])->save();
        }

        if ($user === null && $staff->first_name === 'Demo' && $staff->last_name === 'Person') {
            $staff->forceFill(['first_name' => $firstName, 'last_name' => $lastName])->save();
        }

        $category = $categories[$row['category'] ?? ''] ?? null;
        $fill = [];

        if ($staff->staff_category_id === null && $category !== null) {
            $fill['staff_category_id'] = $category->getKey();
        }

        foreach (['grade', 'employment_type', 'started_on'] as $column) {
            if ($staff->{$column} === null && filled($row[$column] ?? null)) {
                $fill[$column] = $row[$column];
            }
        }

        if ($fill !== []) {
            $staff->forceFill($fill)->save();
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
            $policy = app(LeavePlanner::class)->ensurePolicy($annual);

            if ($organization->isEducational() && (int) $policy->year_start_month === 1 && str_ends_with((string) $policy->name, ' policy')) {
                $policy->update([
                    'year_start_month' => 7,
                    'year_start_day' => 1,
                    'count_mode' => 'working',
                ]);
            }

            $this->entitlements($organization, $policy);

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

        $this->linkArrangements($organization, $shifts);
        $this->leadership($organization);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{0: string, 1: string}
     */
    private function directoryNames(?User $user, string $email, array $row): array
    {
        if ($user !== null) {
            return [$user->first_name, $user->last_name];
        }

        if (filled($row['first_name'] ?? null)) {
            return [(string) $row['first_name'], (string) ($row['last_name'] ?? 'Person')];
        }

        $parts = array_values(array_filter(explode('.', Str::before($email, '@'))));

        return [
            Str::title($parts[0] ?? 'Demo'),
            Str::title($parts[1] ?? 'Person'),
        ];
    }

    private function entitlements(Organization $organization, LeavePolicy $policy): void
    {
        $categories = StaffCategory::withoutTenantScope()
            ->where('organization_id', $organization->getKey())
            ->get()
            ->keyBy('code');
        $finance = Department::withoutTenantScope()
            ->where('organization_id', $organization->getKey())
            ->where('name', 'Finance')
            ->first();

        $rules = $organization->isEducational()
            ? [
                [10, $categories['TEACH'] ?? null, null, null, null, 26],
                [20, $categories['ADMIN'] ?? null, null, null, null, 20],
                [30, $categories['SUPPORT'] ?? null, null, null, null, 15],
                [100, null, null, null, null, 15],
            ]
            : [
                [10, null, null, 'Manager', 120, 30],
                [20, $categories['FINANCE'] ?? null, null, 'Manager', null, 28],
                [30, null, null, 'Manager', null, 26],
                [40, null, $finance, null, null, 20],
                [100, null, null, null, null, 15],
            ];

        foreach ($rules as [$priority, $category, $department, $grade, $months, $days]) {
            LeaveEntitlementRule::withoutTenantScope()->firstOrCreate(
                [
                    'organization_id' => $organization->getKey(),
                    'leave_policy_id' => $policy->getKey(),
                    'priority' => $priority,
                ],
                [
                    'staff_category_id' => $category?->getKey(),
                    'department_id' => $department?->getKey(),
                    'grade' => $grade,
                    'min_service_months' => $months,
                    'entitled_days' => $days,
                    'effective_from' => $policy->effective_from->toDateString(),
                ],
            );
        }
    }

    /**
     * @param  list<Shift>  $shifts
     */
    private function linkArrangements(Organization $organization, array $shifts): void
    {
        $codes = $organization->isEducational()
            ? ['office', 'flexible']
            : ['office', 'field'];

        foreach ($shifts as $index => $shift) {
            $code = $codes[$index] ?? 'office';
            $arrangement = WorkArrangement::withoutTenantScope()
                ->where('organization_id', $organization->getKey())
                ->where('code', $code)
                ->first();

            if ($arrangement !== null && $shift->work_arrangement_id === null) {
                $shift->forceFill(['work_arrangement_id' => $arrangement->getKey()])->save();
            }
        }

        ShiftAssignment::withoutTenantScope()
            ->where('organization_id', $organization->getKey())
            ->whereNull('starts_at')
            ->with('shift')
            ->orderBy('id')
            ->each(function (ShiftAssignment $assignment): void {
                if ($assignment->shift === null) {
                    return;
                }

                $assignment->forceFill([
                    'starts_at' => $assignment->shift->starts_at,
                    'ends_at' => $assignment->shift->ends_at,
                    'crosses_midnight' => $assignment->shift->crosses_midnight,
                    'work_arrangement_id' => $assignment->work_arrangement_id ?? $assignment->shift->work_arrangement_id,
                ])->save();
            });
    }

    private function leadership(Organization $organization): void
    {
        $people = StaffMember::withoutTenantScope()
            ->where('organization_id', $organization->getKey())
            ->orderBy('id')
            ->get()
            ->groupBy('department_id');

        foreach ($people as $departmentId => $members) {
            if ($departmentId === null || $departmentId === '') {
                continue;
            }

            $head = $members->first();
            Department::withoutTenantScope()
                ->whereKey($departmentId)
                ->whereNull('head_staff_member_id')
                ->update(['head_staff_member_id' => $head->getKey()]);

            foreach ($members->skip(1) as $member) {
                if ($member->reports_to_id === null) {
                    $member->forceFill(['reports_to_id' => $head->getKey()])->save();
                }
            }
        }
    }
}
