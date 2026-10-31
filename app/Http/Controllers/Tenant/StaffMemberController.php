<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\StaffStatus;
use App\Enums\WorkType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\StoreStaffMemberRequest;
use App\Http\Requests\Staff\UpdateStaffMemberRequest;
use App\Models\Department;
use App\Models\Location;
use App\Models\StaffMember;
use App\Models\User;
use App\Support\Audit;
use App\Support\TableSort;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StaffMemberController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', StaffMember::class);

        $filters = $request->only(['q', 'department', 'work_type', 'status']);

        $allowed = ['name' => 'last_name', 'number' => 'staff_number', 'status' => 'status', 'type' => 'work_type'];
        $sort = TableSort::from($request, $allowed, 'name');

        $staff = StaffMember::query()
            ->with(['department:id,name', 'primaryLocation:id,name', 'user:id,uuid,status,last_login_at'])
            ->search($filters['q'] ?? null)
            ->when($filters['department'] ?? null, fn ($q, $uuid) => $q->whereHas('department', fn ($d) => $d->where('uuid', $uuid)))
            ->when($filters['work_type'] ?? null, fn ($q, $type) => $q->where('work_type', $type))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status));
        $sort->apply($staff, $allowed);
        $staff = $staff
            ->orderBy('first_name')
            ->paginate(25)
            ->withQueryString();

        return view('tenant.staff.index', [
            'staff' => $staff,
            'filters' => $filters,
            'departments' => Department::query()->orderBy('name')->pluck('name', 'uuid'),
            'workTypes' => WorkType::options(),
            'statuses' => StaffStatus::options(),
            'canCreate' => $request->user()->can('create', StaffMember::class),
            'timezone' => $request->user()->organization->timezone,
            'sort' => $sort,
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', StaffMember::class);

        return view('tenant.staff.form', $this->formData($request) + ['staff' => new StaffMember(['work_type' => WorkType::Office, 'status' => StaffStatus::Active])]);
    }

    public function store(StoreStaffMemberRequest $request): RedirectResponse
    {
        $staff = StaffMember::query()->create($request->toAttributes());

        Audit::log('staff.created', $staff, ['staff_number' => $staff->staff_number], $request->user());

        return redirect()->route('org.staff.show', $staff)->with('status', __('Staff record for :name created.', ['name' => $staff->name]));
    }

    public function show(Request $request, StaffMember $staff): View
    {
        $this->authorize('view', $staff);

        $staff->load(['department', 'primaryLocation', 'user.roles']);

        return view('tenant.staff.show', [
            'staff' => $staff,
            'canUpdate' => $request->user()->can('update', $staff),
            'canViewUser' => $staff->user !== null && $request->user()->can('view', $staff->user),
            'timezone' => $request->user()->organization->timezone,
        ]);
    }

    public function edit(Request $request, StaffMember $staff): View
    {
        $this->authorize('update', $staff);

        $staff->load(['department', 'primaryLocation', 'user']);

        return view('tenant.staff.form', $this->formData($request, $staff) + ['staff' => $staff]);
    }

    public function update(UpdateStaffMemberRequest $request, StaffMember $staff): RedirectResponse
    {
        $staff->fill($request->toAttributes());
        $changes = array_keys($staff->getDirty());
        $staff->save();

        if ($changes !== []) {
            Audit::log('staff.updated', $staff, ['fields' => $changes], $request->user());
        }

        return redirect()->route('org.staff.show', $staff)->with('status', __('Staff record saved.'));
    }

    /**
     * Archive (soft delete). The record stays available for history and can be
     * restored by support; the linked user account is untouched.
     */
    public function destroy(Request $request, StaffMember $staff): RedirectResponse
    {
        $this->authorize('delete', $staff);

        $staff->delete();

        Audit::log('staff.archived', $staff, ['staff_number' => $staff->staff_number], $request->user());

        return redirect()->route('org.staff.index')->with('status', __('Staff record for :name archived.', ['name' => $staff->name]));
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Request $request, ?StaffMember $staff = null): array
    {
        $organizationId = $request->user()->organization_id;

        // Accounts in this organisation that are not yet linked to a staff record
        // (plus the one linked to the record being edited).
        $linkableUsers = User::query()
            ->where('organization_id', $organizationId)
            ->whereDoesntHave('staffMember', fn ($q) => $staff ? $q->whereKeyNot($staff->getKey()) : $q)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get(['id', 'uuid', 'first_name', 'last_name', 'email'])
            ->mapWithKeys(fn (User $u) => [$u->uuid => $u->name.' · '.$u->email])
            ->all();

        return [
            'departments' => Department::query()->where('is_active', true)->orderBy('name')->pluck('name', 'uuid')->all(),
            'locations' => Location::query()->where('is_active', true)->orderBy('name')->pluck('name', 'uuid')->all(),
            'linkableUsers' => $linkableUsers,
            'workTypes' => WorkType::options(),
            'statuses' => StaffStatus::options(),
        ];
    }
}
