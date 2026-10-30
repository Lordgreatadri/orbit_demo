<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\LocationType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Structure\LocationRequest;
use App\Models\Location;
use App\Support\Audit;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Location::class);

        $locations = Location::query()
            ->withCount(['staffMembers' => fn ($q) => $q->where('status', 'active')])
            ->orderBy('name')
            ->paginate(50)
            ->withQueryString();

        return view('tenant.locations.index', [
            'locations' => $locations,
            'types' => LocationType::options(),
            'canManage' => $request->user()->can('create', Location::class),
        ]);
    }

    public function store(LocationRequest $request): RedirectResponse
    {
        $location = Location::query()->create($request->validated());

        Audit::log('location.created', $location, ['name' => $location->name, 'geofence' => $location->hasGeofence()], $request->user());

        return redirect()->route('org.locations.index')->with('status', __('Location ":name" created.', ['name' => $location->name]));
    }

    public function update(LocationRequest $request, Location $location): RedirectResponse
    {
        $location->fill($request->validated());
        $changes = array_keys($location->getDirty());
        $location->save();

        if ($changes !== []) {
            Audit::log('location.updated', $location, ['fields' => $changes], $request->user());
        }

        return redirect()->route('org.locations.index')->with('status', __('Location ":name" saved.', ['name' => $location->name]));
    }

    public function destroy(Request $request, Location $location): RedirectResponse
    {
        $this->authorize('delete', $location);

        if ($location->staffMembers()->exists()) {
            return redirect()->route('org.locations.index')->withErrors([
                'location' => __('":name" is the primary location of existing staff. Reassign them or mark the location inactive instead.', ['name' => $location->name]),
            ]);
        }

        $location->delete();

        Audit::log('location.deleted', null, ['name' => $location->name, 'uuid' => $location->uuid], $request->user());

        return redirect()->route('org.locations.index')->with('status', __('Location ":name" deleted.', ['name' => $location->name]));
    }
}
