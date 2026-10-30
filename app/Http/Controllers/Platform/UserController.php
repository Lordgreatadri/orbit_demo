<?php

namespace App\Http\Controllers\Platform;

use App\Access\GrantResolver;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Access\StorePlatformUserRequest;
use App\Models\Activity;
use App\Models\LoginEvent;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * System-wide user directory for the platform console. Opening it and viewing
 * an account are audited because they cross tenant boundaries.
 */
class UserController extends Controller
{
    public function __construct(private readonly GrantResolver $resolver) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        $actor = $request->user();
        $filters = $request->only(['q', 'organization', 'role', 'status', 'kind']);

        Audit::log('platform.users.viewed', $actor, array_filter($filters), $actor);

        $users = User::query()
            ->with(['organization:id,uuid,name,timezone', 'roles:id,name,scope'])
            ->search($filters['q'] ?? null)
            ->when(($filters['kind'] ?? null) === 'platform', fn ($q) => $q->whereNull('organization_id'))
            ->when(($filters['kind'] ?? null) === 'tenant', fn ($q) => $q->whereNotNull('organization_id'))
            ->when($filters['organization'] ?? null, fn ($q, $uuid) => $q->whereHas('organization', fn ($o) => $o->where('uuid', $uuid)))
            ->when($filters['role'] ?? null, fn ($q, $role) => $q->role($role))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(25)
            ->withQueryString();

        return view('platform.users.index', [
            'users' => $users,
            'filters' => $filters,
            'organizations' => Organization::query()->orderBy('name')->pluck('name', 'uuid')->all(),
            'roles' => Role::query()->orderBy('scope')->orderBy('name')->get()->mapWithKeys(fn (Role $r) => [$r->name => $r->label().' ('.$r->scope->label().')'])->all(),
            'statuses' => collect(UserStatus::cases())->mapWithKeys(fn (UserStatus $s) => [$s->value => $s->label()])->all(),
            'canCreate' => $actor->can('createPlatformAccount', User::class),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('createPlatformAccount', User::class);

        return view('platform.users.create', [
            'roles' => $this->resolver->grantablePlatformRoles($request->user())->mapWithKeys(fn (Role $r) => [$r->name => $r->label()])->all(),
        ]);
    }

    /**
     * Platform accounts are provisioned here (never through public registration).
     * The account receives a password-set link; no password is chosen by the admin.
     */
    public function store(StorePlatformUserRequest $request): RedirectResponse
    {
        $actor = $request->user();

        $user = DB::transaction(function () use ($request, $actor): User {
            $user = new User([
                'organization_id' => null,
                'first_name' => $request->validated('first_name'),
                'last_name' => $request->validated('last_name'),
                'email' => $request->validated('email'),
                'phone' => $request->validated('phone'),
                'password' => Str::password(40),
                'status' => UserStatus::Active,
            ]);
            $user->save();

            $user->assignRole($request->role());

            Audit::log('platform.user.created', $user, ['role' => $request->validated('role')], $actor);

            return $user;
        });

        Password::broker()->sendResetLink(['email' => $user->email]);

        return redirect()
            ->route('admin.users.show', $user)
            ->with('status', __('Platform account for :name created. A link to set their password was e-mailed.', ['name' => $user->name]));
    }

    public function show(Request $request, User $user): View
    {
        $this->authorize('view', $user);

        $actor = $request->user();
        $user->load(['roles', 'permissions', 'organization', 'staffMember' => fn ($q) => $q->withoutGlobalScopes()]);

        Audit::log('platform.user.viewed', $user, [], $actor, $user->organization_id);

        $canManage = $actor->can('manageAccess', $user);

        return view('platform.users.show', [
            'user' => $user,
            'actor' => $actor,
            'canManage' => $canManage,
            'grantableRoles' => $canManage ? $this->resolver->grantableRoles($actor, $user) : collect(),
            'grantablePermissions' => ($canManage ? $this->resolver->grantablePermissions($actor, $user) : collect())->groupBy('module'),
            'moduleLabels' => config('orbit.permission_modules', []),
            'effectivePermissions' => $user->getAllPermissions()->sortBy('name')->values(),
            'recentSignIns' => $actor->can('platform.audit.view')
                ? LoginEvent::query()->where('user_id', $user->getKey())->latest('created_at')->limit(10)->get()
                : collect(),
            'recentActivity' => $actor->can('platform.audit.view')
                ? Activity::query()
                    ->where(fn ($q) => $q
                        ->where(fn ($s) => $s->where('subject_type', $user->getMorphClass())->where('subject_id', $user->getKey()))
                        ->orWhere(fn ($c) => $c->where('causer_type', $user->getMorphClass())->where('causer_id', $user->getKey())))
                    ->with('causer:id,first_name,last_name')
                    ->latest('id')
                    ->limit(10)
                    ->get()
                : collect(),
            'timezone' => $user->organization?->timezone ?? config('app.timezone'),
        ]);
    }
}
