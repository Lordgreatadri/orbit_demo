<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use App\Models\User;
use App\Tenancy\BrandingService;
use App\Tenancy\Origin;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantFinder;
use App\Tenancy\TenantResolution;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hostname → organisation (ADR-0011). Runs before route-model binding.
 * Central hosts leave TenantContext empty. Unknown / unverified / inactive
 * hosts never inherit a tenant. Context is always cleared in terminate().
 */
class IdentifyTenant
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly TenantFinder $finder,
        private readonly BrandingService $branding,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->context->clear();
        $this->branding->clearPreview();
        Origin::restore();

        $resolution = $this->finder->resolve($request);

        if ($resolution->kind === TenantResolution::KIND_UNKNOWN || $resolution->host === '') {
            abort(404);
        }

        if ($resolution->kind === TenantResolution::KIND_UNVERIFIED) {
            abort(404);
        }

        if ($resolution->kind === TenantResolution::KIND_INACTIVE) {
            abort(403, __('This organisation workspace is not available.'));
        }

        if ($resolution->isTenant() && $resolution->organization) {
            $this->context->set($resolution->organization);
            Origin::force(Origin::forHost($resolution->host));
            $this->shareBranding($resolution->organization, $request);
            $this->guardAuthenticatedUser($request, $resolution->organization->getKey());
        } else {
            Origin::force(Origin::forHost($resolution->host));
            $this->shareBranding(null, $request);
        }

        $request->attributes->set('tenant.resolution', $resolution);

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        $this->context->clear();
        $this->branding->clearPreview();
        // Root URL stays on the request origin so post-request assertions and
        // follow-up redirects in the same test match Location headers. The
        // next request's handle() resets Origin before routing.
    }

    private function shareBranding(?Organization $organization, Request $request): void
    {
        $preview = $organization !== null && $request->boolean('branding_preview') && $request->user()?->can('update', $organization);
        $branding = $this->branding->for($organization, $preview);

        View::share('workspaceBranding', $branding);
    }

    private function guardAuthenticatedUser(Request $request, int $organizationId): void
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return;
        }

        if ($user->isPlatformUser()) {
            return;
        }

        if ((int) $user->organization_id !== $organizationId) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            abort(403, __('This workspace belongs to a different organisation.'));
        }
    }
}
