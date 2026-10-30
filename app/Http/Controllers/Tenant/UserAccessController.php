<?php

namespace App\Http\Controllers\Tenant;

use App\Actions\Access\SyncUserPermissions;
use App\Actions\Access\SyncUserRoles;
use App\Actions\Access\UpdateUserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Access\SyncPermissionsRequest;
use App\Http\Requests\Access\SyncRolesRequest;
use App\Http\Requests\Access\UpdateStatusRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class UserAccessController extends Controller
{
    public function roles(SyncRolesRequest $request, User $orgUser, SyncUserRoles $action): RedirectResponse
    {
        $this->authorize('manageAccess', $orgUser);

        $action->handle($request->user(), $orgUser, $request->validated('roles'));

        return redirect()->route('org.users.show', $orgUser)->with('status', __('Roles for :name updated.', ['name' => $orgUser->name]));
    }

    public function permissions(SyncPermissionsRequest $request, User $orgUser, SyncUserPermissions $action): RedirectResponse
    {
        $this->authorize('manageAccess', $orgUser);

        $action->handle($request->user(), $orgUser, $request->validated('permissions'));

        return redirect()->route('org.users.show', $orgUser)->with('status', __('Direct permissions for :name updated.', ['name' => $orgUser->name]));
    }

    public function status(UpdateStatusRequest $request, User $orgUser, UpdateUserStatus $action): RedirectResponse
    {
        $this->authorize('manageAccess', $orgUser);

        $action->handle($request->user(), $orgUser, $request->status(), $request->validated('reason'));

        return redirect()->route('org.users.show', $orgUser)->with('status', __(':name is now :status.', ['name' => $orgUser->name, 'status' => strtolower($request->status()->label())]));
    }
}
