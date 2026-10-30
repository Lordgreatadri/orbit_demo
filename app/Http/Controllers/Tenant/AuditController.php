<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\LoginEventType;
use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\LoginEvent;
use App\Support\AuditQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('audit.view'), 403);

        $organization = $request->user()->organization;
        $filters = AuditQuery::filters($request);

        $activities = AuditQuery::activities($filters)
            ->where('organization_id', $organization->getKey())
            ->paginate(30)
            ->withQueryString();

        return view('tenant.audit.index', [
            'activities' => $activities,
            'filters' => $filters,
            'events' => AuditQuery::eventOptions(Activity::query()->where('organization_id', $organization->getKey())),
            'timezone' => $organization->timezone,
        ]);
    }

    public function logins(Request $request): View
    {
        abort_unless($request->user()->can('audit.logins.view'), 403);

        $organization = $request->user()->organization;
        $filters = AuditQuery::filters($request);

        $events = AuditQuery::loginEvents($filters)
            ->forOrganization($organization->getKey())
            ->paginate(30)
            ->withQueryString();

        return view('tenant.audit.logins', [
            'events' => $events,
            'filters' => $filters,
            'types' => LoginEventType::options(),
            'summary' => AuditQuery::loginSummary(LoginEvent::query()->forOrganization($organization->getKey()), $organization->timezone),
            'timezone' => $organization->timezone,
        ]);
    }
}
