<?php

namespace App\Http\Controllers\App;

use App\Enums\OrganizationType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizations\UpdateOrganizationRequest;
use App\Models\Organization;
use App\Support\Audit;
use DateTimeZone;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * Organisation settings are addressed by UUID (ADR-0003). The policy ensures a
 * user may only reach their own organisation even if they guess another UUID.
 */
class OrganizationSettingsController extends Controller
{
    public function edit(Organization $organization): View
    {
        $this->authorize('view', $organization);

        return view('app.organizations.edit', [
            'organization' => $organization,
            'organizationTypes' => OrganizationType::options(),
            'timezones' => collect(DateTimeZone::listIdentifiers())->mapWithKeys(fn ($tz) => [$tz => str_replace('_', ' ', $tz)])->all(),
            'canUpdate' => request()->user()->can('update', $organization),
        ]);
    }

    public function update(UpdateOrganizationRequest $request, Organization $organization): RedirectResponse
    {
        $this->authorize('update', $organization);

        $organization->fill($request->validated());

        $changes = array_keys($organization->getDirty());

        $organization->save();

        if ($changes !== []) {
            Audit::log('organization.updated', $organization, ['fields' => $changes], $request->user(), $organization->getKey());
        }

        return redirect()
            ->route('app.organizations.edit', $organization)
            ->with('status', __('Organisation settings saved.'));
    }
}
