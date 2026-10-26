<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class MemberController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        $user = $request->user();

        if ($user->isPlatformUser()) {
            Audit::log('platform.members.viewed', $user, [], $user);

            $members = User::query()
                ->whereNotNull('organization_id')
                ->with(['roles', 'organization'])
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->paginate(25)
                ->withQueryString();

            return view('app.members.index', [
                'organization' => null,
                'members' => $members,
                'invitations' => collect(),
                'invitableRoles' => [],
                'canInvite' => false,
                'platformDirectory' => true,
            ]);
        }

        $organization = $user->organization;

        $members = $organization->users()
            ->with('roles')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(25)
            ->withQueryString();

        $invitations = $user->can('viewAny', Invitation::class)
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
            'canInvite' => $user->can('create', Invitation::class),
            'platformDirectory' => false,
        ]);
    }
}
