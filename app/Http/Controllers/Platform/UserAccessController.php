<?php

namespace App\Http\Controllers\Platform;

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
    public function roles(SyncRolesRequest $request, User $user, SyncUserRoles $action): RedirectResponse
    {
        $this->authorize('manageAccess', $user);

        $action->handle($request->user(), $user, $request->validated('roles'));

        return redirect()->route('admin.users.show', $user)->with('status', __('Roles for :name updated.', ['name' => $user->name]));
    }

    public function permissions(SyncPermissionsRequest $request, User $user, SyncUserPermissions $action): RedirectResponse
    {
        $this->authorize('manageAccess', $user);

        $action->handle($request->user(), $user, $request->validated('permissions'));

        return redirect()->route('admin.users.show', $user)->with('status', __('Direct permissions for :name updated.', ['name' => $user->name]));
    }

    public function status(UpdateStatusRequest $request, User $user, UpdateUserStatus $action): RedirectResponse
    {
        $this->authorize('manageAccess', $user);

        $action->handle($request->user(), $user, $request->status(), $request->validated('reason'));

        return redirect()->route('admin.users.show', $user)->with('status', __(':name is now :status.', ['name' => $user->name, 'status' => strtolower($request->status()->label())]));
    }
}
