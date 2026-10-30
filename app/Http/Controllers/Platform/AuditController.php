<?php

namespace App\Http\Controllers\Platform;

use App\Enums\LoginEventType;
use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\LoginEvent;
use App\Models\Organization;
use App\Support\AuditQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('platform.audit.view'), 403);

        $filters = AuditQuery::filters($request);
        $organizationId = $this->organizationId($filters['organization']);

        $activities = AuditQuery::activities($filters)
            ->when($organizationId !== null, fn ($q) => $q->where('organization_id', $organizationId))
            ->when($filters['organization'] === 'platform', fn ($q) => $q->whereNull('organization_id'))
            ->paginate(30)
            ->withQueryString();

        return view('platform.audit.index', [
            'activities' => $activities,
            'filters' => $filters,
            'events' => AuditQuery::eventOptions(Activity::query()),
            'organizations' => ['platform' => '— Platform only —'] + Organization::query()->orderBy('name')->pluck('name', 'uuid')->all(),
            'timezone' => config('app.timezone'),
        ]);
    }

    public function logins(Request $request): View
    {
        abort_unless($request->user()->can('platform.audit.view'), 403);

        $filters = AuditQuery::filters($request);
        $organizationId = $this->organizationId($filters['organization']);

        $events = AuditQuery::loginEvents($filters)
            ->when($organizationId !== null, fn ($q) => $q->where('organization_id', $organizationId))
            ->when($filters['organization'] === 'platform', fn ($q) => $q->whereNull('organization_id'))
            ->paginate(30)
            ->withQueryString();

        return view('platform.audit.logins', [
            'events' => $events,
            'filters' => $filters,
            'types' => LoginEventType::options(),
            'organizations' => ['platform' => '— Platform only —'] + Organization::query()->orderBy('name')->pluck('name', 'uuid')->all(),
            'summary' => AuditQuery::loginSummary(LoginEvent::query(), config('app.timezone')),
            'timezone' => config('app.timezone'),
        ]);
    }

    private function organizationId(?string $uuid): ?int
    {
        if ($uuid === null || $uuid === 'platform') {
            return null;
        }

        return Organization::query()->where('uuid', $uuid)->value('id');
    }
}
