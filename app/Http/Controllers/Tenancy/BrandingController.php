<?php

namespace App\Http\Controllers\Tenancy;

use App\Actions\Tenancy\PublishOrganizationBranding;
use App\Actions\Tenancy\UpdateOrganizationBranding;
use App\Enums\BrandFont;
use App\Enums\BrandThemeMode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenancy\UpdateOrganizationBrandingRequest;
use App\Models\Organization;
use App\Tenancy\BrandingService;
use App\Tenancy\WorkspaceBranding;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BrandingController extends Controller
{
    public function edit(Request $request, Organization $organization, BrandingService $branding): View
    {
        $this->authorize('manageWorkspace', $organization);

        $record = $branding->record($organization);
        $draft = is_array($record->draft) ? $record->draft : [];
        $preview = WorkspaceBranding::preview($organization, $record);

        return view($this->view($request), [
            'organization' => $organization,
            'record' => $record,
            'draft' => $draft,
            'preview' => $preview,
            'themeModes' => BrandThemeMode::options(),
            'fonts' => BrandFont::options(),
            'published' => $branding->for($organization),
        ]);
    }

    public function update(UpdateOrganizationBrandingRequest $request, Organization $organization, UpdateOrganizationBranding $update): RedirectResponse
    {
        $update->saveDraft(
            $organization,
            $request->safe()->except(['logo', 'logo_light', 'logo_dark', 'favicon', 'login_image']),
            [
                'logo' => $request->file('logo'),
                'logo_light' => $request->file('logo_light'),
                'logo_dark' => $request->file('logo_dark'),
                'favicon' => $request->file('favicon'),
                'login_image' => $request->file('login_image'),
            ],
            $request->user(),
        );

        return back()->with('status', __('Draft saved. Preview it, then publish to go live.'));
    }

    public function publish(Request $request, Organization $organization, PublishOrganizationBranding $publish): RedirectResponse
    {
        $this->authorize('manageWorkspace', $organization);

        $publish->handle($organization, $request->user());

        return back()->with('status', __('Branding published. Workspace colours, logo, and login copy are live.'));
    }

    public function preview(Request $request, Organization $organization, BrandingService $branding): View
    {
        $this->authorize('manageWorkspace', $organization);

        $request->merge(['branding_preview' => true]);

        return view('auth.login', [
            'preview' => true,
            'workspaceBranding' => $branding->for($organization, preview: true),
        ]);
    }

    private function view(Request $request): string
    {
        return $request->routeIs('admin.*')
            ? 'platform.organizations.branding'
            : 'tenant.organizations.branding';
    }
}
