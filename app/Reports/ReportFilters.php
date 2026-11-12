<?php

namespace App\Reports;

use App\Analytics\AnalyticsScope;
use App\Enums\AttendanceStatus;
use App\Enums\LeaveStatus;
use App\Enums\VerificationMethod;
use App\Enums\WorkArrangementKind;
use App\Models\LeaveType;
use App\Models\Location;
use App\Models\Shift;
use App\Models\StaffMember;
use App\Models\User;
use App\Models\WorkArrangement;

/**
 * Validated report filters that may only narrow an already authorised AnalyticsScope.
 */
final class ReportFilters
{
    /**
     * @param  array{from?: ?string, to?: ?string, department?: ?string, location?: ?string, person?: ?string, status?: ?string, leave_type?: ?string, leave_status?: ?string, method?: ?string, arrangement?: ?string, shift?: ?string}  $input
     */
    public function __construct(
        public AnalyticsScope $scope,
        public bool $rangeAdjusted,
        public ?string $departmentUuid = null,
        public ?string $locationUuid = null,
        public ?string $personUuid = null,
        public ?string $status = null,
        public ?string $leaveTypeUuid = null,
        public ?string $leaveStatus = null,
        public ?string $method = null,
        public ?string $arrangement = null,
        public ?string $shiftUuid = null,
        public ?int $leaveTypeId = null,
        public ?int $arrangementId = null,
        public ?int $shiftId = null,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @param  list<string>  $allowed
     */
    public static function fromInput(User $user, array $input, array $allowed = []): self
    {
        $from = self::string($input, 'from');
        $to = self::string($input, 'to');
        $department = in_array('department', $allowed, true) ? self::string($input, 'department') : null;
        $location = in_array('location', $allowed, true) ? self::string($input, 'location') : null;
        $person = in_array('person', $allowed, true) ? self::string($input, 'person') : null;

        [$start, $end] = AnalyticsScope::window($user->organization, $from, $to);
        $rangeAdjusted = ($from !== null && $from !== $start->toDateString())
            || ($to !== null && $to !== $end->toDateString());

        $scope = AnalyticsScope::forUser(
            $user,
            $start->toDateString(),
            $end->toDateString(),
            $department,
            $location,
        );

        if ($person !== null) {
            $scope = $scope->narrowToPerson($person);
        }

        $status = in_array('status', $allowed, true) ? self::enumValue($input, 'status', AttendanceStatus::class) : null;
        $leaveStatus = in_array('leave_status', $allowed, true) ? self::enumValue($input, 'leave_status', LeaveStatus::class) : null;
        $method = in_array('method', $allowed, true) ? self::enumValue($input, 'method', VerificationMethod::class) : null;
        $arrangementKind = in_array('arrangement', $allowed, true) ? self::enumValue($input, 'arrangement', WorkArrangementKind::class) : null;
        $leaveTypeUuid = in_array('leave_type', $allowed, true) ? self::string($input, 'leave_type') : null;
        $shiftUuid = in_array('shift', $allowed, true) ? self::string($input, 'shift') : null;

        $leaveTypeId = self::leaveTypeId($scope, $leaveTypeUuid);
        $arrangementId = self::arrangementId($scope, $arrangementKind);
        $shiftId = self::shiftId($scope, $shiftUuid);

        return new self(
            scope: $scope,
            rangeAdjusted: $rangeAdjusted,
            departmentUuid: $department,
            locationUuid: $location,
            personUuid: $person,
            status: $status,
            leaveTypeUuid: $leaveTypeUuid,
            leaveStatus: $leaveStatus,
            method: $method,
            arrangement: $arrangementKind,
            shiftUuid: $shiftUuid,
            leaveTypeId: $leaveTypeId,
            arrangementId: $arrangementId,
            shiftId: $shiftId,
        );
    }

    /**
     * @return array{departments: list<array{uuid: string, name: string}>, locations: list<array{uuid: string, name: string}>, people: list<array{uuid: string, name: string}>, leave_types: list<array{uuid: string, name: string}>, statuses: list<array{value: string, label: string}>, leave_statuses: list<array{value: string, label: string}>, methods: list<array{value: string, label: string}>, arrangements: list<array{value: string, label: string}>, shifts: list<array{uuid: string, name: string}>}
     */
    public function options(array $allowed): array
    {
        $options = [
            'departments' => [],
            'locations' => [],
            'people' => [],
            'leave_types' => [],
            'statuses' => [],
            'leave_statuses' => [],
            'methods' => [],
            'arrangements' => [],
            'shifts' => [],
        ];

        if (in_array('department', $allowed, true)) {
            $options['departments'] = $this->scope->selectableDepartments();
        }

        if (in_array('location', $allowed, true)) {
            $options['locations'] = Location::query()
                ->where('organization_id', $this->scope->organization->getKey())
                ->orderBy('name')
                ->get(['uuid', 'name'])
                ->map(fn (Location $location): array => [
                    'uuid' => $location->uuid,
                    'name' => $location->name,
                ])
                ->all();
        }

        if (in_array('person', $allowed, true) && $this->scope->perspective !== AnalyticsScope::PERSONAL) {
            $options['people'] = StaffMember::query()
                ->where('organization_id', $this->scope->organization->getKey())
                ->when($this->scope->departmentIds !== null, fn ($query) => $query->whereIn(
                    'department_id',
                    $this->scope->departmentIds === [] ? [-1] : $this->scope->departmentIds,
                ))
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->limit(200)
                ->get(['uuid', 'first_name', 'last_name', 'staff_number'])
                ->map(fn (StaffMember $person): array => [
                    'uuid' => $person->uuid,
                    'name' => trim($person->staff_number.' '.$person->name),
                ])
                ->all();
        }

        if (in_array('leave_type', $allowed, true)) {
            $options['leave_types'] = LeaveType::query()
                ->where('organization_id', $this->scope->organization->getKey())
                ->orderBy('name')
                ->get(['uuid', 'name'])
                ->map(fn (LeaveType $type): array => [
                    'uuid' => $type->uuid,
                    'name' => $type->name,
                ])
                ->all();
        }

        if (in_array('status', $allowed, true)) {
            $options['statuses'] = array_map(
                fn (AttendanceStatus $status): array => ['value' => $status->value, 'label' => $status->label()],
                AttendanceStatus::cases(),
            );
        }

        if (in_array('leave_status', $allowed, true)) {
            $options['leave_statuses'] = array_map(
                fn (LeaveStatus $status): array => ['value' => $status->value, 'label' => $status->label()],
                LeaveStatus::cases(),
            );
        }

        if (in_array('method', $allowed, true)) {
            $options['methods'] = array_map(
                fn (VerificationMethod $method): array => ['value' => $method->value, 'label' => $method->label()],
                array_values(array_filter(
                    VerificationMethod::cases(),
                    fn (VerificationMethod $method): bool => ! $method->isReserved(),
                )),
            );
        }

        if (in_array('arrangement', $allowed, true)) {
            $options['arrangements'] = array_map(
                fn (WorkArrangementKind $kind): array => ['value' => $kind->value, 'label' => $kind->label()],
                WorkArrangementKind::cases(),
            );
        }

        if (in_array('shift', $allowed, true)) {
            $options['shifts'] = Shift::query()
                ->where('organization_id', $this->scope->organization->getKey())
                ->when($this->scope->departmentIds !== null, fn ($query) => $query->whereIn(
                    'department_id',
                    $this->scope->departmentIds === [] ? [-1] : $this->scope->departmentIds,
                ))
                ->orderBy('name')
                ->limit(100)
                ->get(['uuid', 'name'])
                ->map(fn (Shift $shift): array => [
                    'uuid' => $shift->uuid,
                    'name' => $shift->name,
                ])
                ->all();
        }

        return $options;
    }

    /**
     * @return array<string, ?string>
     */
    public function selected(): array
    {
        return [
            'from' => $this->scope->from->toDateString(),
            'to' => $this->scope->to->toDateString(),
            'department' => $this->departmentUuid,
            'location' => $this->locationUuid,
            'person' => $this->personUuid,
            'status' => $this->status,
            'leave_type' => $this->leaveTypeUuid,
            'leave_status' => $this->leaveStatus,
            'method' => $this->method,
            'arrangement' => $this->arrangement,
            'shift' => $this->shiftUuid,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function query(): array
    {
        return array_filter(
            $this->selected(),
            fn (?string $value): bool => $value !== null && $value !== '',
        );
    }

    private static function string(array $input, string $key): ?string
    {
        $value = $input[$key] ?? null;

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }

    /**
     * @param  class-string<\BackedEnum>  $enum
     */
    private static function enumValue(array $input, string $key, string $enum): ?string
    {
        $value = self::string($input, $key);

        if ($value === null) {
            return null;
        }

        foreach ($enum::cases() as $case) {
            if ($case->value === $value) {
                return $value;
            }
        }

        return null;
    }

    private static function leaveTypeId(AnalyticsScope $scope, ?string $uuid): ?int
    {
        if ($uuid === null) {
            return null;
        }

        $id = LeaveType::query()
            ->where('organization_id', $scope->organization->getKey())
            ->where('uuid', $uuid)
            ->value('id');

        return $id === null ? null : (int) $id;
    }

    private static function arrangementId(AnalyticsScope $scope, ?string $kind): ?int
    {
        if ($kind === null) {
            return null;
        }

        $id = WorkArrangement::query()
            ->where('organization_id', $scope->organization->getKey())
            ->where('kind', $kind)
            ->value('id');

        return $id === null ? null : (int) $id;
    }

    private static function shiftId(AnalyticsScope $scope, ?string $uuid): ?int
    {
        if ($uuid === null) {
            return null;
        }

        $id = Shift::query()
            ->where('organization_id', $scope->organization->getKey())
            ->where('uuid', $uuid)
            ->when($scope->departmentIds !== null, fn ($query) => $query->whereIn(
                'department_id',
                $scope->departmentIds === [] ? [-1] : $scope->departmentIds,
            ))
            ->value('id');

        return $id === null ? null : (int) $id;
    }
}
