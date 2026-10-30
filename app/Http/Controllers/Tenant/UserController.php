<?php

namespace App\Http\Controllers\Tenant;

use App\Access\GrantResolver;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Invitation;
use App\Models\LoginEvent;
use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Login accounts of the current organisation. Separate from the staff register:
 * a user is an identity with roles; a staff member is a person record.
 */
class UserController extends Controller
{
    public function __construct(private readonly GrantResolver $resolver) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        $actor = $request->user();
        $organization = $actor->organization;
        $filters = $request->only(['q', 'role', 'status']);

        $users = User::query()
            ->where('organization_id', $organization->getKey())
            ->with(['roles:id,name,scope', 'staffMember:id,user_id,staff_number'])
            ->search($filters['q'] ?? null)
            ->when($filters['role'] ?? null, fn ($q, $role) => $q->role($role))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(25)
            ->withQueryString();

        $invitations = $actor->can('viewAny', Invitation::class)
            ? Invitation::query()
                ->with('inviter:id,first_name,last_name')
                ->whereNull('accepted_at')
                ->latest()
                ->limit(50)
                ->get()
            : collect();

        return view('tenant.users.index', [
            'organization' => $organization,
            'users' => $users,
            'filters' => $filters,
            'roles' => Role::query()->tenant()->orderBy('name')->get()->mapWithKeys(fn (Role $r) => [$r->name => $r->label()])->all(),
            'statuses' => collect(UserStatus::cases())->mapWithKeys(fn (UserStatus $s) => [$s->value => $s->label()])->all(),
            'invitations' => $invitations,
            'invitableRoles' => Role::query()->tenant()->orderBy('name')->get()->mapWithKeys(fn (Role $r) => [$r->name => $r->label()])->all(),
            'canInvite' => $actor->can('create', Invitation::class),
            'timezone' => $organization->timezone,
        ]);
    }

    public function show(Request $request, User $orgUser): View
    {
        $this->authorize('view', $orgUser);

        $actor = $request->user();
        $orgUser->load(['roles', 'permissions', 'staffMember.department']);

        $canManage = $actor->can('manageAccess', $orgUser);

        $grantableRoles = $canManage ? $this->resolver->grantableRoles($actor, $orgUser) : collect();
        $grantablePermissions = $canManage ? $this->resolver->grantablePermissions($actor, $orgUser) : collect();

        return view('tenant.users.show', [
            'user' => $orgUser,
            'actor' => $actor,
            'canManage' => $canManage,
            'grantableRoles' => $grantableRoles,
            'grantablePermissions' => $grantablePermissions->groupBy('module'),
            'moduleLabels' => config('orbit.permission_modules', []),
            'effectivePermissions' => $orgUser->getAllPermissions()->sortBy('name')->values(),
            'recentSignIns' => $actor->can('audit.logins.view')
                ? LoginEvent::query()->where('user_id', $orgUser->getKey())->latest('created_at')->limit(10)->get()
                : collect(),
            'recentActivity' => $actor->can('audit.view')
                ? Activity::query()
                    ->where('organization_id', $orgUser->organization_id)
                    ->where(fn ($q) => $q
                        ->where(fn ($s) => $s->where('subject_type', $orgUser->getMorphClass())->where('subject_id', $orgUser->getKey()))
                        ->orWhere(fn ($c) => $c->where('causer_type', $orgUser->getMorphClass())->where('causer_id', $orgUser->getKey())))
                    ->with('causer:id,first_name,last_name')
                    ->latest('id')
                    ->limit(10)
                    ->get()
                : collect(),
            'timezone' => $actor->organization->timezone,
        ]);
    }
}
