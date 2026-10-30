<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Single post-login landing point. Routes each account type to its own
 * workspace so tenant and platform screens never share a controller tree.
 */
class WorkspaceController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->isPlatformUser()) {
            return redirect()->route($user->can('platform.access') ? 'admin.dashboard' : 'app.profile');
        }

        return redirect()->route('org.dashboard');
    }
}
