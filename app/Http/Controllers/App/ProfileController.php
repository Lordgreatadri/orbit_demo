<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\LoginEvent;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user()->loadMissing(['roles', 'organization', 'staffMember.department']);

        $recentSignIns = LoginEvent::query()
            ->where('user_id', $user->getKey())
            ->latest('created_at')
            ->limit(10)
            ->get();

        return view('app.profile', [
            'user' => $user,
            'organization' => $user->organization,
            'recentSignIns' => $recentSignIns,
            'permissions' => $user->getAllPermissions()->sortBy('name')->values(),
            'timezone' => $user->organization?->timezone ?? config('app.timezone'),
        ]);
    }
}
