<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $organization = $user->organization;

        $memberCount = $organization?->users()->count() ?? 0;
        $pendingInvitations = $organization?->invitations()->pending()->count() ?? 0;

        return view('app.dashboard', [
            'user' => $user,
            'organization' => $organization,
            'memberCount' => $memberCount,
            'pendingInvitations' => $pendingInvitations,
        ]);
    }
}
