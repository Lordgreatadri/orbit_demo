<?php

namespace App\Http\Controllers\Tenancy;

use App\Actions\Tenancy\MakeDomainPrimary;
use App\Actions\Tenancy\RegisterOrganizationDomain;
use App\Actions\Tenancy\UpdateOrganizationDomain;
use App\Actions\Tenancy\VerifyCustomDomain;
use App\Enums\DomainType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenancy\StoreOrganizationDomainRequest;
use App\Models\Organization;
use App\Models\OrganizationDomain;
use App\Tenancy\Hostname;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DomainController extends Controller
{
    public function edit(Request $request, Organization $organization): View
    {
        $this->authorize('manageWorkspace', $organization);

        $organization->load(['domains' => fn ($q) => $q->orderByDesc('is_primary')->orderBy('domain')]);

        return view($this->view($request, 'domains'), [
            'organization' => $organization,
            'domains' => $organization->domains,
            'baseDomain' => Hostname::baseDomain(),
            'types' => [
                DomainType::Alias->value => DomainType::Alias->label(),
                DomainType::CustomDomain->value => DomainType::CustomDomain->label(),
            ],
        ]);
    }

    public function store(StoreOrganizationDomainRequest $request, Organization $organization, RegisterOrganizationDomain $register): RedirectResponse
    {
        $domain = $register->handle(
            $organization,
            $request->string('domain')->toString(),
            DomainType::from($request->string('type')->toString()),
            $request->user(),
            $request->boolean('make_primary'),
        );

        $status = match (true) {
            $domain->is_primary => __('Hostname registered and set as the primary workspace URL.'),
            $domain->type === DomainType::CustomDomain => __('Hostname registered. Add the TXT record shown below, then choose Check DNS. This address stays offline until it is verified.'),
            default => __('Hostname registered. This address is active and visitors can use it now.'),
        };

        return back()->with('status', $status);
    }

    public function primary(Request $request, Organization $organization, OrganizationDomain $domain, MakeDomainPrimary $makePrimary): RedirectResponse
    {
        $this->authorize('manageWorkspace', $organization);
        abort_unless($domain->organization_id === $organization->getKey(), 404);

        $makePrimary->handle($domain, $request->user());

        return back()->with('status', __('Primary workspace URL updated. Branding links now use this hostname.'));
    }

    public function verify(Request $request, Organization $organization, OrganizationDomain $domain, VerifyCustomDomain $verify): RedirectResponse
    {
        $this->authorize('manageWorkspace', $organization);
        abort_unless($domain->organization_id === $organization->getKey(), 404);

        $verify->handle($domain, $request->user());

        return back()->with('status', __('Custom domain verified and activated. It now accepts workspace traffic.'));
    }

    public function deactivate(Request $request, Organization $organization, OrganizationDomain $domain, UpdateOrganizationDomain $update): RedirectResponse
    {
        $this->authorize('manageWorkspace', $organization);
        abort_unless($domain->organization_id === $organization->getKey(), 404);

        $update->deactivate($domain, $request->user());

        return back()->with('status', __('Hostname deactivated. This address no longer accepts workspace traffic.'));
    }

    public function activate(Request $request, Organization $organization, OrganizationDomain $domain, UpdateOrganizationDomain $update): RedirectResponse
    {
        $this->authorize('manageWorkspace', $organization);
        abort_unless($domain->organization_id === $organization->getKey(), 404);

        $update->activate($domain, $request->user());

        return back()->with('status', __('Hostname activated. Trusted hosts were refreshed — this address now accepts workspace traffic.'));
    }

    public function destroy(Request $request, Organization $organization, OrganizationDomain $domain, UpdateOrganizationDomain $update): RedirectResponse
    {
        $this->authorize('manageWorkspace', $organization);
        abort_unless($domain->organization_id === $organization->getKey(), 404);

        $update->delete($domain, $request->user());

        return back()->with('status', __('Hostname removed. It will no longer resolve to this workspace.'));
    }

    private function view(Request $request, string $page): string
    {
        return $request->routeIs('admin.*')
            ? 'platform.organizations.'.$page
            : 'tenant.organizations.'.$page;
    }
}
