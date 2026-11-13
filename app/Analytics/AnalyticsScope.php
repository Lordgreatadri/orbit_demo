<?php

namespace App\Analytics;

use App\Models\Department;
use App\Models\Location;
use App\Models\Organization;
use App\Models\StaffMember;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Who may see which rows for one reporting window.
 *
 * Organisation administrators, and anyone granted organisation-wide analytics
 * without heading a department, see the tenant. A non-admin who heads one or
 * more departments is limited to those departments. Everyone else sees only
 * their own person record. A requested department or location can narrow that
 * scope. It cannot widen it, and it cannot select another organisation.
 */
final class AnalyticsScope
{
    public const ORGANISATION = 'organisation';

    public const DEPARTMENT = 'department';

    public const PERSONAL = 'personal';

    /**
     * @param  list<int>|null  $departmentIds  null means every department in the organisation
     */
    public function __construct(
        public Organization $organization,
        public User $viewer,
        public string $perspective,
        public ?array $departmentIds,
        public ?int $staffMemberId,
        public ?int $locationId,
        public Carbon $from,
        public Carbon $to,
    ) {}

    public static function forUser(
        User $user,
        ?string $from = null,
        ?string $to = null,
        ?string $departmentUuid = null,
        ?string $locationUuid = null,
    ): self {
        $organization = $user->organization;
        [$start, $end] = self::window($organization, $from, $to);
        $audience = self::audience($user);

        $departmentIds = $audience['departmentIds'];
        $requested = self::departmentId($organization, $departmentUuid);

        if ($requested !== null) {
            if ($departmentIds === null || in_array($requested, $departmentIds, true)) {
                $departmentIds = [$requested];
            }
        }

        return new self(
            $organization,
            $user,
            $audience['perspective'],
            $departmentIds,
            $audience['staffMemberId'],
            self::locationId($organization, $locationUuid),
            $start,
            $end,
        );
    }

    /**
     * Department ids a non-admin department head may see.
     * Null means the caller is not department-restricted.
     *
     * @return list<int>|null
     */
    public static function restrictingDepartmentIds(User $user): ?array
    {
        return self::audience($user)['departmentIds'];
    }

    /**
     * Organisation administrators, and managers who do not head a department,
     * may use any department. A department head may use only a department they head.
     */
    public static function departmentAllowed(User $user, ?int $departmentId): bool
    {
        $ids = self::restrictingDepartmentIds($user);

        if ($ids === null) {
            return true;
        }

        return $departmentId !== null && in_array($departmentId, $ids, true);
    }

    public function withDates(Carbon $from, Carbon $to): self
    {
        return new self(
            $this->organization,
            $this->viewer,
            $this->perspective,
            $this->departmentIds,
            $this->staffMemberId,
            $this->locationId,
            $from->copy()->startOfDay(),
            $to->copy()->startOfDay(),
        );
    }

    /**
     * Narrow to one person already inside this scope. A foreign or out-of-scope
     * UUID is ignored so filters cannot widen access.
     */
    public function narrowToPerson(?string $staffUuid): self
    {
        if ($staffUuid === null || $staffUuid === '' || $this->perspective === self::PERSONAL) {
            return $this;
        }

        $person = StaffMember::query()
            ->where('organization_id', $this->organization->getKey())
            ->where('uuid', $staffUuid)
            ->first(['id', 'department_id']);

        if ($person === null) {
            return $this;
        }

        if ($this->departmentIds !== null && ! in_array((int) $person->department_id, $this->departmentIds, true)) {
            return $this;
        }

        return new self(
            $this->organization,
            $this->viewer,
            $this->perspective,
            $this->departmentIds,
            (int) $person->getKey(),
            $this->locationId,
            $this->from->copy(),
            $this->to->copy(),
        );
    }

    public function label(): string
    {
        if ($this->perspective === self::PERSONAL) {
            return 'Your record';
        }

        if ($this->departmentIds === null) {
            return 'Whole organisation';
        }

        $names = Department::query()
            ->where('organization_id', $this->organization->getKey())
            ->whereIn('id', $this->departmentIds === [] ? [-1] : $this->departmentIds)
            ->orderBy('name')
            ->pluck('name');

        return $names->isEmpty() ? 'No department in scope' : $names->join(', ');
    }

    /**
     * @return list<array{uuid: string, name: string}>
     */
    public function selectableDepartments(): array
    {
        if ($this->perspective === self::PERSONAL) {
            return [];
        }

        return Department::query()
            ->where('organization_id', $this->organization->getKey())
            ->when($this->perspective === self::DEPARTMENT, function ($query): void {
                $query->whereIn('id', $this->allowedDepartmentIds());
            })
            ->orderBy('name')
            ->get(['uuid', 'name'])
            ->map(fn (Department $department): array => [
                'uuid' => $department->uuid,
                'name' => $department->name,
            ])
            ->all();
    }

    /**
     * Departments this viewer may query before an optional narrower filter.
     *
     * @return list<int>
     */
    public function allowedDepartmentIds(): array
    {
        if ($this->perspective !== self::DEPARTMENT) {
            return Department::query()
                ->where('organization_id', $this->organization->getKey())
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        $audience = self::audience($this->viewer);

        return $audience['departmentIds'] ?? [];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function window(Organization $organization, ?string $from, ?string $to): array
    {
        $timezone = $organization->timezone ?: 'UTC';
        $start = Carbon::parse($from ?: now($timezone)->startOfMonth()->toDateString(), $timezone)->startOfDay();
        $end = Carbon::parse($to ?: now($timezone)->toDateString(), $timezone)->startOfDay();

        if ($start->gt($end) || $start->diffInDays($end) > 61) {
            $start = $end->copy()->subDays(30);
        }

        return [$start, $end];
    }

    /**
     * Scope that was allowed to produce an export artifact.
     *
     * @return array{organization_id: int, perspective: string, department_ids: list<int>|null, staff_member_id: int|null}
     */
    public function toGenerationScope(): array
    {
        return [
            'organization_id' => (int) $this->organization->getKey(),
            'perspective' => $this->perspective,
            'department_ids' => $this->departmentIds,
            'staff_member_id' => $this->staffMemberId,
        ];
    }

    /**
     * The current scope covers a stored artifact when every department and
     * person in that artifact is still inside the current window.
     *
     * @param  array<string, mixed>|null  $stored
     */
    public function coversGeneration(?array $stored): bool
    {
        if (! is_array($stored) || ! array_key_exists('department_ids', $stored)) {
            return false;
        }

        if ((int) ($stored['organization_id'] ?? 0) !== (int) $this->organization->getKey()) {
            return false;
        }

        $artifactDepartments = $stored['department_ids'];

        if ($artifactDepartments === null) {
            if ($this->departmentIds !== null) {
                return false;
            }
        } elseif ($this->departmentIds !== null) {
            foreach ($artifactDepartments as $id) {
                if (! in_array((int) $id, $this->departmentIds, true)) {
                    return false;
                }
            }
        }

        if ($this->staffMemberId !== null && (int) ($stored['staff_member_id'] ?? 0) !== (int) $this->staffMemberId) {
            return false;
        }

        return true;
    }

    public function cacheKey(string $metric): string
    {
        $departments = $this->departmentIds === null ? 'all' : implode(',', $this->departmentIds);

        return implode(':', [
            'orbit-analytics',
            'v1',
            $this->organization->getKey(),
            $this->viewer->getKey(),
            $this->perspective,
            $departments,
            $this->staffMemberId ?? 'none',
            $this->locationId ?? 'none',
            $this->from->toDateString(),
            $this->to->toDateString(),
            $metric,
        ]);
    }

    /**
     * @return array{perspective: string, departmentIds: list<int>|null, staffMemberId: int|null}
     */
    private static function audience(User $user): array
    {
        $organizationWide = $user->hasRole('organization_admin') || $user->can('organization.update');
        $staffId = $user->staffMember?->getKey();

        if ($user->can('analytics.view') && $organizationWide) {
            return [
                'perspective' => self::ORGANISATION,
                'departmentIds' => null,
                'staffMemberId' => null,
            ];
        }

        if ($user->can('analytics.view') && $staffId !== null) {
            $headed = Department::query()
                ->where('organization_id', $user->organization_id)
                ->where('head_staff_member_id', $staffId)
                ->where('is_active', true)
                ->orderBy('id')
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            if ($headed !== []) {
                return [
                    'perspective' => self::DEPARTMENT,
                    'departmentIds' => $headed,
                    'staffMemberId' => null,
                ];
            }
        }

        if ($user->can('analytics.view')) {
            return [
                'perspective' => self::ORGANISATION,
                'departmentIds' => null,
                'staffMemberId' => null,
            ];
        }

        return [
            'perspective' => self::PERSONAL,
            'departmentIds' => null,
            'staffMemberId' => $staffId !== null ? (int) $staffId : 0,
        ];
    }

    private static function departmentId(Organization $organization, ?string $uuid): ?int
    {
        if ($uuid === null || $uuid === '') {
            return null;
        }

        $id = Department::query()
            ->where('organization_id', $organization->getKey())
            ->where('uuid', $uuid)
            ->value('id');

        return $id === null ? null : (int) $id;
    }

    private static function locationId(Organization $organization, ?string $uuid): ?int
    {
        if ($uuid === null || $uuid === '') {
            return null;
        }

        $id = Location::query()
            ->where('organization_id', $organization->getKey())
            ->where('uuid', $uuid)
            ->value('id');

        return $id === null ? null : (int) $id;
    }
}
