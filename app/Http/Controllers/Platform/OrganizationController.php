<?php

namespace App\Http\Controllers\Platform;

use App\Enums\OrganizationStatus;
use App\Enums\OrganizationType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizations\UpdateOrganizationStatusRequest;
use App\Models\Activity;
use App\Models\Organization;
use App\Models\StaffMember;
use App\Models\User;
use App\Support\Audit;
use App\Support\TableSort;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrganizationController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAnyAsPlatform', Organization::class);

        $filters = $request->only(['q', 'status', 'type']);

        $allowed = ['name' => 'name', 'status' => 'status', 'type' => 'type', 'created' => 'id'];
        $sort = TableSort::from($request, $allowed, 'created', 'desc');

        $organizations = Organization::query()
            ->withCount('users')
            ->with('domains')
            ->when($filters['q'] ?? null, function ($q, $term): void {
                $like = '%'.mb_strtolower(trim($term)).'%';
                $q->where(fn ($w) => $w->whereRaw('LOWER(name) LIKE ?', [$like])->orWhereRaw('LOWER(contact_email) LIKE ?', [$like])->orWhereRaw('LOWER(slug) LIKE ?', [$like]));
            })
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['type'] ?? null, fn ($q, $type) => $q->where('type', $type));
        $sort->apply($organizations, $allowed);
        $organizations = $organizations
            ->paginate(25)
            ->withQueryString();

        return view('platform.organizations.index', [
            'organizations' => $organizations,
            'filters' => $filters,
            'statuses' => collect(OrganizationStatus::cases())->mapWithKeys(fn (OrganizationStatus $s) => [$s->value => $s->label()])->all(),
            'types' => OrganizationType::options(),
            'sort' => $sort,
        ]);
    }

    public function show(Request $request, Organization $organization): View
    {
        $this->authorize('viewAsPlatform', $organization);

        Audit::log('platform.organization.viewed', $organization, [], $request->user(), $organization->getKey());

        $usersByStatus = User::query()
            ->where('organization_id', $organization->getKey())
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $administrators = User::query()
            ->where('organization_id', $organization->getKey())
            ->role('organization_admin')
            ->orderBy('last_name')
            ->limit(10)
            ->get(['id', 'uuid', 'first_name', 'last_name', 'email', 'status', 'last_login_at']);

        return view('platform.organizations.show', [
            'organization' => $organization,
            'counts' => [
                'users' => (int) $usersByStatus->sum(),
                'users_active' => (int) ($usersByStatus['active'] ?? 0),
                'users_suspended' => (int) ($usersByStatus['suspended'] ?? 0),
                'staff' => StaffMember::withoutTenantScope()->where('organization_id', $organization->getKey())->count(),
                'departments' => $organization->departments()->withoutGlobalScopes()->count(),
                'locations' => $organization->locations()->withoutGlobalScopes()->count(),
            ],
            'administrators' => $administrators,
            'recentActivity' => $request->user()->can('platform.audit.view')
                ? Activity::query()->where('organization_id', $organization->getKey())->with('causer:id,first_name,last_name')->latest('id')->limit(10)->get()
                : collect(),
            'canUpdateStatus' => $request->user()->can('updateStatus', $organization),
            'statusOptions' => collect([OrganizationStatus::Active, OrganizationStatus::Suspended, OrganizationStatus::Closed])
                ->reject(fn (OrganizationStatus $s) => $s === $organization->status)
                ->mapWithKeys(fn (OrganizationStatus $s) => [$s->value => $s->label()])
                ->all(),
        ]);
    }

    public function updateStatus(UpdateOrganizationStatusRequest $request, Organization $organization): RedirectResponse
    {
        $this->authorize('updateStatus', $organization);

        $status = $request->status();

        if ($organization->status === $status) {
            return redirect()->route('admin.organizations.show', $organization);
        }

        if ($organization->status === OrganizationStatus::PendingVerification && $status === OrganizationStatus::Active) {
            // Activating manually also records verification; the admin takes responsibility.
            $organization->forceFill(['verified_at' => now()]);
        }

        if ($organization->status === OrganizationStatus::Closed && $status !== OrganizationStatus::Active) {
            throw ValidationException::withMessages(['status' => __('A closed organisation can only be reactivated.')]);
        }

        DB::transaction(function () use ($organization, $status, $request): void {
            $previous = $organization->status;
            $organization->forceFill(['status' => $status])->save();

            if ($status !== OrganizationStatus::Active && config('session.driver') === 'database') {
                // Suspension/closure signs out every account in the organisation immediately.
                DB::table(config('session.table', 'sessions'))
                    ->whereIn('user_id', User::query()->where('organization_id', $organization->getKey())->select('id'))
                    ->delete();
            }

            Audit::log('platform.organization.status_updated', $organization, [
                'from' => $previous->value,
                'to' => $status->value,
                'reason' => $request->validated('reason'),
            ], $request->user(), $organization->getKey());
        });

        return redirect()
            ->route('admin.organizations.show', $organization)
            ->with('status', __(':name is now :status.', ['name' => $organization->name, 'status' => strtolower($status->label())]));
    }
}
