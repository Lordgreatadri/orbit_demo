<?php

namespace App\Http\Controllers\Tenant;

use App\Actions\Notifications\SaveCommunicationPolicy;
use App\Actions\Notifications\SaveNotificationTemplate;
use App\Actions\Notifications\SaveUserNotificationPreferences;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workforce\UpdateCommunicationPolicyRequest;
use App\Http\Requests\Workforce\UpdateNotificationPreferencesRequest;
use App\Http\Requests\Workforce\UpdateNotificationTemplateRequest;
use App\Http\Requests\Workforce\UpdateOwnNotificationPreferencesRequest;
use App\Models\CommunicationPolicy;
use App\Models\NotificationPreference;
use App\Models\NotificationTemplate;
use App\Models\UserNotificationPreference;
use App\Support\Audit;
use App\Workforce\Communication\Inbox;
use App\Workforce\WorkforceEvents;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WorkforceNotificationController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $category = $request->string('category')->toString();
        $category = array_key_exists($category, WorkforceEvents::categories()) ? $category : null;

        $preferences = NotificationPreference::query()
            ->whereIn('event', WorkforceEvents::keys())
            ->get()
            ->keyBy('event');

        $own = UserNotificationPreference::query()
            ->where('user_id', $user->getKey())
            ->get()
            ->keyBy('category');

        $policy = CommunicationPolicy::forOrganization($user->organization);

        return view('tenant.notifications.index', [
            'events' => WorkforceEvents::catalog(),
            'definitions' => WorkforceEvents::definitions(),
            'categories' => WorkforceEvents::categories(),
            'preferences' => $preferences,
            'own' => $own,
            'policy' => $policy,
            'canManage' => $user->can('notifications.manage'),
            'inbox' => Inbox::page($user, $category),
            'unread' => Inbox::unreadCount($user),
            'category' => $category,
            'templates' => $user->can('notifications.manage')
                ? NotificationTemplate::query()->where('channel', 'all')->get()->keyBy('event')
                : collect(),
        ]);
    }

    public function update(UpdateNotificationPreferencesRequest $request): RedirectResponse
    {
        $input = $request->validated();
        $saved = [];

        foreach (WorkforceEvents::keys() as $event) {
            $row = $input['events'][$event] ?? [];
            $choice = [
                'mail' => filter_var($row['mail'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'database' => filter_var($row['database'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'sms' => filter_var($row['sms'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'push' => filter_var($row['push'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ];
            NotificationPreference::query()->updateOrCreate(
                ['organization_id' => $request->user()->organization_id, 'event' => $event],
                $choice,
            );
            $saved[$event] = $choice;
        }

        Audit::log('notifications.channels_updated', null, ['channels' => $saved], $request->user());

        return back()->with('status', 'Notification channels saved. SMS sends only when a platform provider is enabled. Push waits for a provider plug.');
    }

    public function preferences(UpdateOwnNotificationPreferencesRequest $request, SaveUserNotificationPreferences $save): RedirectResponse
    {
        $save($request->user(), $request->validated('categories'));

        return back()->with('status', 'Your notification choices were saved. Required notices stay on.');
    }

    public function policy(UpdateCommunicationPolicyRequest $request, SaveCommunicationPolicy $save): RedirectResponse
    {
        $save($request->user(), $request->flags());

        return back()->with('status', 'Organisation notification policy saved.');
    }

    public function template(UpdateNotificationTemplateRequest $request, SaveNotificationTemplate $save): RedirectResponse
    {
        $save(
            $request->user(),
            $request->string('event')->toString(),
            trim($request->string('subject')->toString()),
            trim($request->string('body')->toString()),
        );

        return back()->with('status', 'Notification wording saved.');
    }

    public function read(Request $request, string $notification): RedirectResponse
    {
        $user = $request->user();
        Inbox::markRead($user, Inbox::find($user, $notification));

        return back()->with('status', 'Notification marked as read.');
    }

    public function open(Request $request, string $notification): RedirectResponse
    {
        $user = $request->user();
        $notice = Inbox::find($user, $notification);
        Inbox::markRead($user, $notice);
        $target = Inbox::safeUrl($notice->data['url'] ?? null, $user);

        if ($target === null) {
            return back()->with('status', 'Notification marked as read.');
        }

        return redirect()->to($target);
    }

    public function readAll(Request $request): RedirectResponse
    {
        Inbox::markAllRead($request->user());

        return back()->with('status', 'All notifications marked as read.');
    }
}
