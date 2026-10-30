<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Read-only view of the organisation roles and what each grants. Roles are
 * global and maintained in the platform console (ADR-0009 §5); administrators
 * assign them per user from the Users screen.
 */
class RoleController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Role::class);

        $organizationId = $request->user()->organization_id;

        $roles = Role::query()->tenant()->with('permissions:id,name,module,description')->orderBy('name')->get();

        // Members per role inside this organisation only (one grouped query).
        $usage = DB::table('model_has_roles')
            ->join('users', 'users.id', '=', 'model_has_roles.model_id')
            ->where('model_has_roles.model_type', (new User)->getMorphClass())
            ->where('users.organization_id', $organizationId)
            ->select('model_has_roles.role_id', DB::raw('count(*) as total'))
            ->groupBy('model_has_roles.role_id')
            ->pluck('total', 'role_id');

        return view('tenant.roles.index', [
            'roles' => $roles,
            'usage' => $usage,
            'moduleLabels' => config('orbit.permission_modules', []),
            'grantable' => $roles->pluck('name')->all(),
        ]);
    }
}
