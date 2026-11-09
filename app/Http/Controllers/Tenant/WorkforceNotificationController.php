<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Workforce\UpdateNotificationPreferencesRequest;
use App\Models\NotificationPreference;
use App\Workforce\WorkforceEvents;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WorkforceNotificationController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $preferences = NotificationPreference::query()
            ->whereIn('event', WorkforceEvents::keys())
            ->get()
            ->keyBy('event');

        return view('tenant.notifications.index', [
            'events' => WorkforceEvents::catalog(),
            'preferences' => $preferences,
            'canManage' => $user->can('notifications.manage'),
            'inbox' => $user->notifications()->limit(30)->get(),
        ]);
    }

    public function update(UpdateNotificationPreferencesRequest $request): RedirectResponse
    {
        $input = $request->validated();

        foreach (WorkforceEvents::keys() as $event) {
            $row = $input['events'][$event] ?? [];
            NotificationPreference::query()->updateOrCreate(
                ['organization_id' => $request->user()->organization_id, 'event' => $event],
                [
                    'mail' => filter_var($row['mail'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    'database' => filter_var($row['database'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    'sms' => filter_var($row['sms'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    'push' => filter_var($row['push'] ?? false, FILTER_VALIDATE_BOOLEAN),
                ],
            );
        }

        return back()->with('status', 'Notification preferences saved. SMS sends only when a platform provider is enabled. Push waits for a provider plug.');
    }
}
