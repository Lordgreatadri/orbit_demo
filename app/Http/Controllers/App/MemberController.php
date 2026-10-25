<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class MemberController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        $organization = $request->user()->organization;

        $members = $organization->users()
            ->with('roles')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(25)
            ->withQueryString();

        $invitations = $request->user()->can('viewAny', Invitation::class)
            ? Invitation::query()
                ->with('inviter:id,first_name,last_name')
                ->whereNull('accepted_at')
                ->latest()
                ->limit(50)
                ->get()
            : collect();

        return view('app.members.index', [
            'organization' => $organization,
            'members' => $members,
            'invitations' => $invitations,
            'invitableRoles' => collect(config('orbit.invitable_roles', []))
                ->mapWithKeys(fn (string $role) => [$role => config('orbit.roles.tenant.'.$role, $role)])
                ->all(),
            'canInvite' => $request->user()->can('create', Invitation::class),
        ]);
    }
}
