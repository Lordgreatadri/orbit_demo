<?php

namespace Tests\Feature\Notifications;

use App\Integrations\Sms\Exceptions\PermanentSmsException;
use App\Integrations\Sms\Exceptions\RetryableSmsException;
use App\Jobs\Workforce\DeliverWorkforceNotice;
use App\Models\CommunicationPolicy;
use App\Models\DatabaseNotification;
use App\Models\LeavePolicy;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Location;
use App\Models\NotificationPreference;
use App\Models\NotificationTemplate;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\SmsMessage;
use App\Models\SmsProvider;
use App\Models\StaffMember;
use App\Models\User;
use App\Models\UserNotificationPreference;
use App\Models\WorkforceNoticeDelivery;
use App\Notifications\WorkforceMessage;
use App\Workforce\Communication\DeliveryFailure;
use App\Workforce\Communication\Inbox;
use App\Workforce\Communication\NotificationCopy;
use App\Workforce\WorkforceNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WorkforceCommunicationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-04 09:00:00', 'Africa/Accra'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    #[Test]
    public function leave_schedule_and_attendance_notices_reach_the_right_people(): void
    {
        [$admin, $member, $staff] = $this->people();
        $outsider = $this->createMember();
        $type = $this->leaveType($admin);
        Notification::fake();

        $this->actingAs($member)->post(route('org.leave.store'), [
            'leave_type_uuid' => $type->uuid,
            'starts_on' => '2026-12-02',
            'ends_on' => '2026-12-01',
            'reason' => 'Backwards',
        ])->assertSessionHasErrors();
        Notification::assertNothingSent();

        $this->actingAs($member)->post(route('org.leave.store'), [
            'leave_type_uuid' => $type->uuid,
            'starts_on' => '2026-12-02',
            'ends_on' => '2026-12-02',
            'reason' => 'A day away',
        ])->assertSessionHas('status');

        Notification::assertSentTo($admin, WorkforceMessage::class, fn (WorkforceMessage $message): bool => $message->event === 'leave.submitted');
        Notification::assertNotSentTo($member, WorkforceMessage::class, fn (WorkforceMessage $message): bool => $message->event === 'leave.submitted');
        Notification::assertNotSentTo($outsider, WorkforceMessage::class);

        $request = LeaveRequest::query()->firstOrFail();
        $this->actingAs($member)->put(route('org.leave.amend', $request), [
            'starts_on' => '2026-12-03',
            'ends_on' => '2026-12-03',
            'reason' => 'Moved',
        ])->assertSessionHas('status');
        Notification::assertSentTo($admin, WorkforceMessage::class, fn (WorkforceMessage $message): bool => $message->event === 'leave.amended');

        $this->actingAs($member)->post(route('org.leave.withdraw', $request))->assertSessionHas('status');
        Notification::assertSentTo($admin, WorkforceMessage::class, fn (WorkforceMessage $message): bool => $message->title === 'Leave request withdrawn');
        Notification::assertNotSentTo($member, WorkforceMessage::class, fn (WorkforceMessage $message): bool => $message->title === 'Leave request withdrawn');

        $this->actingAs($member)->post(route('org.leave.store'), [
            'leave_type_uuid' => $type->uuid,
            'starts_on' => '2026-12-10',
            'ends_on' => '2026-12-12',
            'reason' => 'Annual',
        ])->assertSessionHas('status');
        $approved = LeaveRequest::query()->where('status', 'submitted')->firstOrFail();
        $this->actingAs($admin)->post(route('org.leave.decide', $approved), [
            'decision' => 'approve',
            'decision_note' => 'secret note',
        ])->assertSessionHas('status');
        Notification::assertSentTo($member, WorkforceMessage::class, function (WorkforceMessage $message): bool {
            return $message->event === 'leave.decided'
                && $message->title === 'Leave approved'
                && str_contains($message->body, '10 December 2026')
                && str_contains($message->body, '12 December 2026')
                && ! str_contains($message->body, 'secret note');
        });

        $this->actingAs($admin)->post(route('org.leave.cancel', $approved), [
            'decision_note' => 'changed plans',
        ])->assertSessionHas('status');
        Notification::assertSentTo($member, WorkforceMessage::class, fn (WorkforceMessage $message): bool => $message->title === 'Approved leave cancelled' && ! str_contains($message->body, 'changed plans'));

        $this->actingAs($member)->post(route('org.leave.store'), [
            'leave_type_uuid' => $type->uuid,
            'starts_on' => '2026-12-15',
            'ends_on' => '2026-12-15',
            'reason' => 'No',
        ])->assertSessionHas('status');
        $rejected = LeaveRequest::query()->where('status', 'submitted')->firstOrFail();
        $this->actingAs($admin)->post(route('org.leave.decide', $rejected), [
            'decision' => 'reject',
            'decision_note' => 'not this week',
        ])->assertSessionHas('status');
        Notification::assertSentTo($member, WorkforceMessage::class, fn (WorkforceMessage $message): bool => $message->title === 'Leave not approved' && ! str_contains($message->body, 'not this week'));

        Notification::fake();
        $this->actingAs($admin)->post(route('org.schedules.store'), [
            'name' => 'Day duty',
            'starts_at' => '08:00',
            'ends_at' => '17:00',
            'effective_from' => '2026-11-01',
        ])->assertSessionHas('status');
        $shift = Shift::query()->where('name', 'Day duty')->firstOrFail();
        $this->actingAs($admin)->post(route('org.schedules.publish', $shift))->assertSessionHas('status');
        Notification::assertNothingSent();
        $this->actingAs($admin)->post(route('org.schedules.assign', $shift), [
            'staff_uuid' => $staff->uuid,
            'work_date' => '2026-11-05',
        ])->assertSessionHas('status');
        Notification::assertSentTo($member, WorkforceMessage::class, fn (WorkforceMessage $message): bool => $message->event === 'schedule.published');
        Notification::assertNotSentTo($admin, WorkforceMessage::class);
        Notification::assertNotSentTo($outsider, WorkforceMessage::class);
        $assignment = ShiftAssignment::query()->firstOrFail();
        $this->actingAs($admin)->delete(route('org.schedules.assignments.destroy', $assignment))->assertSessionHas('status');
        Notification::assertSentTo($member, WorkforceMessage::class, fn (WorkforceMessage $message): bool => $message->event === 'schedule.changed' && $message->title === 'Schedule changed');

        Notification::fake();
        $location = Location::factory()->forOrganization($admin->organization)->create();
        $staff->update(['primary_location_id' => $location->getKey()]);
        $this->actingAs($member)->post(route('org.attendance.check-in'), ['methods' => ['manual']])->assertSessionHasErrors('attendance');
        $this->actingAs($member)->post(route('org.attendance.check-in'), ['methods' => ['manual']])->assertSessionHasErrors('attendance');
        Notification::assertSentToTimes($member, WorkforceMessage::class, 1);
        Notification::assertSentTo($member, WorkforceMessage::class, fn (WorkforceMessage $message): bool => $message->event === 'attendance.rejected');
        Notification::assertNotSentTo($outsider, WorkforceMessage::class);
    }

    #[Test]
    public function a_rolled_back_transaction_does_not_queue_a_notice_and_a_provider_failure_does_not_undo_the_business_row(): void
    {
        [$admin, $member] = $this->people();
        Notification::fake();

        try {
            DB::transaction(function () use ($admin): void {
                DeliverWorkforceNotice::dispatch(
                    (int) $admin->organization_id,
                    (int) $admin->getKey(),
                    'leave.decided',
                    'Leave approved',
                    'Approved.',
                    'rollback-leave',
                )->afterCommit();
                throw new \RuntimeException('fail the leave');
            });
            $this->fail('The transaction should have rolled back.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('fail the leave', $exception->getMessage());
        }

        Notification::assertNothingSent();
        $this->assertSame(0, WorkforceNoticeDelivery::query()->count());

        DB::transaction(function () use ($admin): void {
            DeliverWorkforceNotice::dispatch(
                (int) $admin->organization_id,
                (int) $admin->getKey(),
                'leave.decided',
                'Leave approved',
                'Approved.',
                'committed-leave',
            )->afterCommit();
        });
        Notification::assertSentTo($admin, WorkforceMessage::class);

        $member->update(['last_name' => 'Kept', 'phone' => 'not-a-phone']);
        SmsProvider::factory()->ready()->create(['is_active' => true]);
        NotificationPreference::query()->create([
            'organization_id' => $member->organization_id,
            'event' => 'leave.decided',
            'mail' => false,
            'database' => false,
            'sms' => true,
            'push' => false,
        ]);

        $this->assertTrue(DeliveryFailure::shouldRetry(new RetryableSmsException('timeout')));
        $this->assertFalse(DeliveryFailure::shouldRetry(new PermanentSmsException('rejected number')));

        $notifier = app(WorkforceNotifier::class);
        $notifier->deliver($member, 'leave.decided', 'Leave approved', 'Approved.', 'sms-permanent-1');
        $notifier->deliver($member, 'leave.decided', 'Leave approved', 'Approved.', 'sms-permanent-1');
        $this->assertSame('Kept', $member->fresh()->last_name);
        $this->assertSame(1, WorkforceNoticeDelivery::query()->where('dedupe_key', 'sms-permanent-1')->where('channel', 'sms')->count());
        $this->assertSame('failed', WorkforceNoticeDelivery::query()->where('dedupe_key', 'sms-permanent-1')->value('status'));
        $this->assertSame('permanent', WorkforceNoticeDelivery::query()->where('dedupe_key', 'sms-permanent-1')->value('failure_kind'));
        $this->assertSame(0, SmsMessage::query()->count());
    }

    #[Test]
    public function an_approved_leave_request_stays_approved_when_sms_fails(): void
    {
        [$admin, $member] = $this->people();
        $type = $this->leaveType($admin);
        NotificationPreference::query()->create([
            'organization_id' => $admin->organization_id,
            'event' => 'leave.decided',
            'mail' => false,
            'database' => true,
            'sms' => true,
            'push' => false,
        ]);
        Http::preventStrayRequests();
        Http::fake([
            'https://frogapi.wigal.com.gh/api/v3/sms/send' => Http::response(['status' => 'ERROR'], 503),
            'https://frogapi.wigal.com.gh/api/v3/balance' => Http::response(['status' => 'SUCCESS', 'data' => ['bundles' => ['SMS' => 40], 'cashbalance' => 1]]),
        ]);
        SmsProvider::factory()->ready()->create([
            'is_active' => true,
            'max_attempts' => 1,
            'backoff_seconds' => [1],
        ]);

        $this->actingAs($member)->post(route('org.leave.store'), [
            'leave_type_uuid' => $type->uuid,
            'starts_on' => '2026-12-02',
            'ends_on' => '2026-12-02',
            'reason' => 'A day away',
        ])->assertSessionHas('status');
        $request = LeaveRequest::query()->firstOrFail();
        $this->actingAs($admin)->post(route('org.leave.decide', $request), [
            'decision' => 'approve',
            'decision_note' => 'Granted',
        ])->assertSessionHas('status');

        $this->assertSame('approved', $request->fresh()->status->value);
        $this->assertSame('Granted', $request->fresh()->decision_note);
    }

    #[Test]
    public function optional_notices_follow_personal_and_tenant_choices_and_required_notices_do_not(): void
    {
        [$admin, $member] = $this->people();
        Notification::fake();
        UserNotificationPreference::query()->create([
            'organization_id' => $member->organization_id,
            'user_id' => $member->getKey(),
            'category' => 'leave',
            'mail' => false,
            'database' => false,
            'sms' => false,
        ]);

        $notifier = app(WorkforceNotifier::class);
        $notifier->deliver($member, 'leave.decided', 'Leave approved', 'Approved.', 'mandatory-leave');
        $notifier->deliver($member, 'leave.balance_warning', 'Leave balance is low', 'Two days remain.', 'optional-leave');
        Notification::assertSentToTimes($member, WorkforceMessage::class, 1);
        Notification::assertSentTo($member, WorkforceMessage::class, fn (WorkforceMessage $message): bool => $message->event === 'leave.decided');

        CommunicationPolicy::query()->create([
            'organization_id' => $member->organization_id,
            'email_enabled' => true,
            'sms_enabled' => true,
            'in_app_enabled' => true,
            'leave_notifications_enabled' => false,
            'schedule_notifications_enabled' => true,
            'attendance_reminders_enabled' => true,
            'announcements_enabled' => true,
        ]);
        app(WorkforceNotifier::class)->deliver($member, 'leave.decided', 'Leave approved', 'Approved.', 'policy-off');
        Notification::assertSentToTimes($member, WorkforceMessage::class, 1);
    }

    #[Test]
    public function notification_records_preferences_and_templates_cannot_cross_tenants(): void
    {
        [$admin, $member] = $this->people();
        $outsider = $this->createMember();
        $outsider->notify(new WorkforceMessage($outsider->organization, 'leave.decided', 'Secret org notice', 'Private body'));
        $foreign = DatabaseNotification::query()->where('notifiable_id', $outsider->getKey())->firstOrFail();
        $this->assertSame((int) $outsider->organization_id, (int) $foreign->organization_id);

        $this->actingAs($admin)->get(route('org.notifications.index'))
            ->assertOk()
            ->assertDontSee('Secret org notice');
        $this->actingAs($admin)->post(route('org.notifications.read', $foreign->uuid))->assertNotFound();
        $this->assertNull($foreign->fresh()->read_at);

        UserNotificationPreference::query()->create([
            'organization_id' => $outsider->organization_id,
            'user_id' => $outsider->getKey(),
            'category' => 'leave',
            'mail' => true,
            'database' => true,
            'sms' => true,
        ]);
        $this->actingAs($member)->put(route('org.notifications.policy'), [
            'email_enabled' => '0',
        ])->assertForbidden();
        $this->actingAs($admin)->put(route('org.notifications.preferences'), [
            'categories' => [
                'leave' => ['mail' => '0', 'database' => '1', 'sms' => '0'],
                'schedule' => ['mail' => '1', 'database' => '1', 'sms' => '0'],
                'attendance' => ['mail' => '1', 'database' => '1', 'sms' => '0'],
                'announcements' => ['mail' => '1', 'database' => '1', 'sms' => '0'],
            ],
            'user_id' => $outsider->getKey(),
            'organization_id' => $outsider->organization_id,
        ])->assertSessionHas('status');
        $this->assertSame(1, (int) UserNotificationPreference::withoutTenantScope()->where('user_id', $outsider->getKey())->where('sms', true)->count());
        $this->assertSame((int) $admin->organization_id, (int) UserNotificationPreference::query()->where('user_id', $admin->getKey())->where('category', 'leave')->value('organization_id'));
        $this->assertDatabaseHas('activity_log', ['event' => 'notifications.preferences_updated']);

        NotificationTemplate::query()->create([
            'organization_id' => $outsider->organization_id,
            'event' => 'leave.decided',
            'channel' => 'all',
            'subject' => 'Outsider title',
            'body' => 'Outsider body',
        ]);
        $this->actingAs($admin)->put(route('org.notifications.template'), [
            'event' => 'leave.decided',
            'subject' => 'Our title',
            'body' => 'Hello {{person_name}}',
        ])->assertSessionHas('status');
        $this->assertSame('Outsider body', NotificationTemplate::withoutTenantScope()->where('organization_id', $outsider->organization_id)->value('body'));
        $this->assertSame(
            'Our title',
            NotificationCopy::compose($admin->organization, 'leave.decided', 'Default', 'Default body', ['person_name' => 'Ama'])[0],
        );
        $this->assertSame(
            ['Outsider title', 'Outsider body'],
            NotificationCopy::compose($outsider->organization, 'leave.decided', 'Default', 'Default body', []),
        );

        $this->actingAs($admin)->put(route('org.notifications.policy'), [
            'email_enabled' => '1',
            'sms_enabled' => '0',
            'in_app_enabled' => '1',
            'leave_notifications_enabled' => '1',
            'schedule_notifications_enabled' => '1',
            'attendance_reminders_enabled' => '0',
            'announcements_enabled' => '1',
        ])->assertSessionHas('status');
        $this->assertDatabaseHas('activity_log', ['event' => 'notifications.policy_updated']);
        $properties = (string) DB::table('activity_log')->where('event', 'notifications.policy_updated')->value('properties');
        $this->assertStringNotContainsString('password', strtolower($properties));
        $this->assertStringNotContainsString('credential', strtolower($properties));
    }

    #[Test]
    public function the_inbox_is_paged_readable_and_the_unread_count_does_not_load_payloads(): void
    {
        [$admin, $member] = $this->people();
        $member->notify(new WorkforceMessage($member->organization, 'leave.decided', 'Leave approved', 'Your leave was approved.', 'https://evil.test/phish', 'Open'));
        $member->notify(new WorkforceMessage($member->organization, 'schedule.published', 'Schedule assigned', 'Day duty is on your schedule.'));

        $notice = DatabaseNotification::query()->where('notifiable_id', $member->getKey())->where('event', 'leave.decided')->firstOrFail();
        $this->actingAs($member)->get(route('org.notifications.index'))
            ->assertOk()
            ->assertSee('Leave approved')
            ->assertSee('Unread')
            ->assertSee('Mark as read')
            ->assertDontSee('https://evil.test/phish', false);
        $this->actingAs($member)->get(route('org.notifications.index', ['category' => 'schedule']))
            ->assertOk()
            ->assertSee('Schedule assigned')
            ->assertDontSee(route('org.notifications.read', $notice->uuid), false);
        $this->actingAs($member)->post(route('org.notifications.read', $notice->uuid))->assertSessionHas('status');
        $this->assertNotNull($notice->fresh()->read_at);

        $this->actingAs($member)->get(route('org.dashboard'))
            ->assertOk()
            ->assertSee('Notifications')
            ->assertSee('Schedule assigned')
            ->assertSee('Unread');

        for ($i = 0; $i < 16; $i++) {
            $admin->notify(new WorkforceMessage($admin->organization, 'attendance.confirmed', 'Notice '.$i, 'Body '.$i));
        }
        $this->actingAs($admin)->get(route('org.notifications.index'))
            ->assertOk()
            ->assertSee('Notice 15')
            ->assertDontSee('Notice 0');
        $this->actingAs($admin)->get(route('org.notifications.index', ['page' => 2]))
            ->assertOk()
            ->assertSee('Notice 0');

        $empty = $this->createMember($admin->organization);
        $this->actingAs($empty)->get(route('org.notifications.index'))
            ->assertOk()
            ->assertSee('No notifications');

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->assertSame(1, Inbox::unreadCount($member));
        $logged = collect(DB::getQueryLog())->filter(fn (array $query): bool => str_contains(strtolower($query['query']), 'notifications'));
        $this->assertCount(1, $logged);
        $sql = strtolower((string) $logged->first()['query']);
        $this->assertStringContainsString('count', $sql);
        $this->assertStringNotContainsString('data', $sql);

        $filled = NotificationCopy::fill('Hello {{person_name}} {{system(\'id\')}} <?php @php', ['person_name' => 'Ama']);
        $this->assertStringContainsString('Ama', $filled);
        $this->assertStringNotContainsString('<?', $filled);
        $this->assertStringNotContainsString('{{', $filled);
    }

    #[Test]
    public function a_template_keeps_the_submitted_event_and_rejects_an_array_body(): void
    {
        [$admin] = $this->people();

        $this->actingAs($admin)->from(route('org.notifications.index'))->put(route('org.notifications.template'), [
            'event' => 'leave.decided',
            'subject' => ['not', 'text'],
            'body' => ['also', 'an array'],
        ])->assertRedirect(route('org.notifications.index'))->assertSessionHasErrors(['subject', 'body']);

        $this->actingAs($admin)->from(route('org.notifications.index'))->put(route('org.notifications.template'), [
            'event' => 'leave.decided',
            'subject' => 'Kept title',
            'body' => '<?php echo 1;',
        ])->assertRedirect(route('org.notifications.index'))->assertSessionHasErrors('body');

        $this->actingAs($admin)->get(route('org.notifications.index'))
            ->assertOk()
            ->assertSee('value="leave.decided" selected', false)
            ->assertDontSee('value="attendance.confirmed" selected', false)
            ->assertSee('Kept title')
            ->assertSee('<?php echo 1;');

        $this->actingAs($admin)->put(route('org.notifications.template'), [
            'event' => 'leave.decided',
            'subject' => 'Kept title',
            'body' => 'Hello {{person_name}}',
        ])->assertSessionHas('status');

        $this->assertSame(1, NotificationTemplate::query()->count());
        $this->assertSame('leave.decided', NotificationTemplate::query()->value('event'));
        $this->assertSame('Kept title', NotificationTemplate::query()->value('subject'));
        $this->assertSame('Hello {{person_name}}', NotificationTemplate::query()->value('body'));

        $this->actingAs($admin)->put(route('org.notifications.template'), [
            'event' => 'leave.amended',
            'body' => '<?php',
        ])->assertSessionHasErrors('body');
        $this->assertSame('leave.decided', NotificationTemplate::query()->value('event'));
        $this->assertSame('Hello {{person_name}}', NotificationTemplate::query()->value('body'));
    }

    /**
     * @return array{0: User, 1: User, 2: StaffMember}
     */
    private function people(): array
    {
        $admin = $this->createOrganizationAdmin();
        $admin->organization->update(['timezone' => 'Africa/Accra']);
        $member = $this->createMember($admin->organization);
        $staff = StaffMember::factory()->forOrganization($admin->organization)->linkedTo($member)->create();

        return [$admin, $member, $staff];
    }

    private function leaveType(User $admin): LeaveType
    {
        $this->actingAs($admin)->post(route('org.leave.types.store'), [
            'name' => 'Annual',
            'code' => 'annual',
            'requires_balance' => '1',
            'default_days' => '10',
        ])->assertSessionHas('status');

        $type = LeaveType::query()->where('code', 'ANNUAL')->firstOrFail();
        $this->assertNotNull(LeavePolicy::query()->where('leave_type_id', $type->getKey())->first());

        return $type;
    }
}
