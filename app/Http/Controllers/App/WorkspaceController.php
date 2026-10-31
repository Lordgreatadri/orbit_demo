<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Tenancy\Origin;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Single post-login landing point. Organisation accounts must be on a
 * hostname registered to their organisation; platform accounts stay central.
 */
class WorkspaceController extends Controller
{
    public function __invoke(Request $request, TenantContext $context): RedirectResponse
    {
        $user = $request->user();

        if ($user->isPlatformUser()) {
            abort_if($context->has(), 403);

            return redirect()->route($user->can('platform.access') ? 'admin.dashboard' : 'app.profile');
        }

        $organization = $user->organization;
        abort_if($organization === null, 403);

        if (! $context->has() || $context->id() !== $organization->getKey()) {
            return redirect()->away(Origin::url($organization, '/login'));
        }

        return redirect()->route('org.dashboard');
    }
}
