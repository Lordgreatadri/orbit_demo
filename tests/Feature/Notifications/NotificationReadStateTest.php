<?php

namespace Tests\Feature\Notifications;

use App\Models\DatabaseNotification;
use App\Models\NotificationReadAudit;
use App\Models\User;
use App\Models\WorkforceNoticeDelivery;
use App\Notifications\WorkforceMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NotificationReadStateTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_notice_is_addressed_to_the_user_and_a_browser_cannot_choose_the_recipient(): void
    {
        $admin = $this->createOrganizationAdmin();
        $member = $this->createMember($admin->organization);
        $outsider = $this->createMember();
        $member->notify(new WorkforceMessage($member->organization, 'leave.decided', 'Leave approved', 'Your leave was approved.', '/org/leave', 'View leave'));
        $outsider->notify(new WorkforceMessage($outsider->organization, 'leave.decided', 'Foreign leave', 'Hidden.'));

        $own = DatabaseNotification::query()->where('notifiable_id', $member->getKey())->firstOrFail();
        $foreign = DatabaseNotification::query()->where('notifiable_id', $outsider->getKey())->firstOrFail();

        $this->assertSame(User::class, $own->notifiable_type);
        $this->assertSame((int) $member->getKey(), (int) $own->notifiable_id);
        $this->assertSame((int) $member->organization_id, (int) $own->organization_id);
        $this->assertNull($own->read_at);
        $this->assertNotSame((int) $member->organization_id, (int) $foreign->organization_id);

        $this->actingAs($member)->post(route('org.notifications.open', $foreign->uuid), [
            'user_id' => $member->getKey(),
            'recipient_id' => $member->getKey(),
            'organization_id' => $outsider->organization_id,
        ])->assertNotFound();

        $this->assertNull($foreign->fresh()->read_at);
        $this->assertSame(0, NotificationReadAudit::withoutTenantScope()->count());
        $this->actingAs($member)->get(route('org.notifications.index'))->assertDontSee('Foreign leave');
    }

    #[Test]
    public function the_first_read_is_audited_and_a_repeat_or_mark_all_does_not_audit_it_again(): void
    {
        $admin = $this->createOrganizationAdmin();
        $member = $this->createMember($admin->organization);

        foreach (['One', 'Two', 'Three'] as $title) {
            $member->notify(new WorkforceMessage($member->organization, 'schedule.published', $title, 'Assigned.'));
        }

        $first = DatabaseNotification::query()->where('notifiable_id', $member->getKey())->where('data->title', 'One')->firstOrFail();
        $this->assertNull($first->read_at);

        $this->actingAs($member)->post(route('org.notifications.read', $first->uuid))->assertSessionHas('status');
        $this->actingAs($member)->post(route('org.notifications.read', $first->uuid))->assertSessionHas('status');

        $this->assertNotNull($first->fresh()->read_at);
        $this->assertSame(1, NotificationReadAudit::withoutTenantScope()->where('notification_id', $first->getKey())->count());
        $audit = NotificationReadAudit::withoutTenantScope()->where('notification_id', $first->getKey())->firstOrFail();
        $this->assertSame((int) $member->organization_id, (int) $audit->organization_id);
        $this->assertSame((int) $member->getKey(), (int) $audit->user_id);
        $this->assertNotNull($audit->read_at);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($member)->post(route('org.notifications.read-all'))->assertSessionHas('status');
        $inserts = collect(DB::getQueryLog())->filter(
            fn (array $query): bool => str_contains(strtolower($query['query']), 'notification_read_audits')
                && str_contains(strtolower($query['query']), 'insert'),
        );
        $this->assertCount(1, $inserts);
        $payloadSelects = collect(DB::getQueryLog())->filter(
            fn (array $query): bool => str_contains(strtolower($query['query']), 'from "notifications"')
                && str_contains(strtolower($query['query']), '"data"'),
        );
        $this->assertCount(0, $payloadSelects);

        $this->assertSame(3, NotificationReadAudit::withoutTenantScope()->where('user_id', $member->getKey())->count());
        $this->assertSame(0, DatabaseNotification::query()->where('notifiable_id', $member->getKey())->whereNull('read_at')->count());

        $this->actingAs($member)->post(route('org.notifications.read-all'))->assertSessionHas('status');
        $this->assertSame(3, NotificationReadAudit::withoutTenantScope()->where('user_id', $member->getKey())->count());
    }

    #[Test]
    public function opening_a_notice_marks_it_read_and_follows_only_a_safe_action_url(): void
    {
        $admin = $this->createOrganizationAdmin();
        $member = $this->createMember($admin->organization);
        $member->notify(new WorkforceMessage($member->organization, 'leave.decided', 'Leave approved', 'Approved.', '/org/leave', 'View leave'));
        $member->notify(new WorkforceMessage($member->organization, 'leave.decided', 'Outside', 'No.', 'https://evil.test/phish', 'Open'));
        $member->notify(new WorkforceMessage($member->organization, 'schedule.published', 'Missing page', 'Gone.', '/org/not-a-real-page', 'Open'));

        $approved = DatabaseNotification::query()->where('data->title', 'Leave approved')->firstOrFail();
        $this->actingAs($member)->post(route('org.notifications.open', $approved->uuid))
            ->assertRedirect('/org/leave');
        $this->assertNotNull($approved->fresh()->read_at);
        $this->assertSame(1, NotificationReadAudit::withoutTenantScope()->where('notification_id', $approved->getKey())->count());
        $this->actingAs($member)->post(route('org.notifications.open', $approved->uuid))
            ->assertRedirect('/org/leave');
        $this->assertSame(1, NotificationReadAudit::withoutTenantScope()->where('notification_id', $approved->getKey())->count());

        $outside = DatabaseNotification::query()->where('data->title', 'Outside')->firstOrFail();
        $this->actingAs($member)->from(route('org.dashboard'))
            ->post(route('org.notifications.open', $outside->uuid))
            ->assertRedirect(route('org.dashboard'));
        $this->assertNotNull($outside->fresh()->read_at);

        $missing = DatabaseNotification::query()->where('data->title', 'Missing page')->firstOrFail();
        $this->actingAs($member)->post(route('org.notifications.open', $missing->uuid))
            ->assertRedirect('/org/not-a-real-page');
        $this->actingAs($member)->get('/org/not-a-real-page')->assertNotFound();
    }

    #[Test]
    public function the_bell_lists_recent_notices_and_read_state_is_separate_from_delivery(): void
    {
        $admin = $this->createOrganizationAdmin();
        $member = $this->createMember($admin->organization);
        $member->notify(new WorkforceMessage($member->organization, 'attendance.confirmed', 'Attendance recorded', 'Your check-in was recorded.'));
        $notice = DatabaseNotification::query()->where('notifiable_id', $member->getKey())->firstOrFail();

        WorkforceNoticeDelivery::query()->create([
            'organization_id' => $member->organization_id,
            'user_id' => $member->getKey(),
            'dedupe_key' => 'attendance-delivery-1',
            'channel' => 'sms',
            'status' => 'failed',
            'failure_kind' => 'permanent',
            'attempts' => 1,
        ]);

        $this->actingAs($member)->get(route('org.dashboard'))
            ->assertOk()
            ->assertSee('aria-haspopup="dialog"', false)
            ->assertSee('Recent notifications', false)
            ->assertSee('Attendance recorded')
            ->assertSee('Unread')
            ->assertSee('1 unread')
            ->assertSee('View all')
            ->assertSee(route('org.notifications.open', $notice->uuid), false);

        $this->actingAs($member)->post(route('org.notifications.read', $notice->uuid));
        $this->assertSame('failed', WorkforceNoticeDelivery::query()->where('dedupe_key', 'attendance-delivery-1')->value('status'));
        $this->assertNotNull($notice->fresh()->read_at);

        $this->actingAs($member)->get(route('org.dashboard'))
            ->assertOk()
            ->assertSee('Read')
            ->assertSee('0 unread');

        $empty = $this->createMember($admin->organization);
        $this->actingAs($empty)->get(route('org.notifications.index'))
            ->assertOk()
            ->assertSee('No notifications')
            ->assertSee('No notifications yet.')
            ->assertSee('View all');
    }
}
