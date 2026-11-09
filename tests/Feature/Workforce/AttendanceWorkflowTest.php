<?php

namespace Tests\Feature\Workforce;

use App\Enums\UserStatus;
use App\Enums\WorkType;
use App\Models\AttendanceEvent;
use App\Models\AttendanceOtp;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\AttendanceToken;
use App\Models\Beacon;
use App\Models\BoundDevice;
use App\Models\FieldActivity;
use App\Models\FieldAssignment;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Location;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\StaffMember;
use App\Models\User;
use App\Models\WifiNetwork;
use App\Models\WorkforcePolicy;
use App\Notifications\WorkforceMessage;
use App\Workforce\QrSessionService;
use App\Workforce\QrSvg;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AttendanceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-04 08:05:00', 'Africa/Accra'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    #[Test]
    public function an_office_button_press_is_rejected_and_a_qr_check_in_is_accepted_once(): void
    {
        [$admin, $member, $staff, $location] = $this->office();

        $this->actingAs($member)
            ->post(route('org.attendance.check-in'), ['methods' => ['manual']])
            ->assertSessionHasErrors('attendance');
        $this->assertDatabaseCount('attendance_records', 0);

        $token = $this->openSession($admin, $location);

        $this->actingAs($member)
            ->post(route('org.attendance.check-in'), ['methods' => ['qr'], 'token' => $token])
            ->assertRedirect()
            ->assertSessionHas('status');

        $record = AttendanceRecord::query()->where('staff_member_id', $staff->getKey())->firstOrFail();
        $this->assertSame('present', $record->status->value);
        $this->assertSame(0, $record->late_minutes);
        $this->assertDatabaseHas('activity_log', ['event' => 'attendance.checked_in']);

        $this->actingAs($member)
            ->post(route('org.attendance.check-in'), ['methods' => ['qr'], 'token' => $token])
            ->assertSessionHasErrors('attendance');
        $this->assertSame(1, AttendanceRecord::query()->count());

        $this->actingAs($member)->post(route('org.attendance.check-out'))->assertSessionHas('status');
        $this->assertNotNull($record->fresh()->check_out_at);
    }

    #[Test]
    public function expired_codes_late_arrivals_and_other_organisations_are_rejected(): void
    {
        [$admin, $member, $staff, $location] = $this->office();
        $token = $this->openSession($admin, $location, 30);

        Carbon::setTestNow(now()->addMinutes(2));
        $this->actingAs($member)
            ->post(route('org.attendance.check-in'), ['methods' => ['qr'], 'token' => $token])
            ->assertSessionHasErrors('attendance');

        Carbon::setTestNow(Carbon::parse('2026-11-04 09:00:00', 'Africa/Accra'));
        $lateToken = $this->openSession($admin, $location);
        $this->actingAs($member)
            ->post(route('org.attendance.check-in'), ['methods' => ['qr'], 'token' => $lateToken])
            ->assertSessionHas('status');
        $this->assertSame('late', AttendanceRecord::query()->where('staff_member_id', $staff->getKey())->firstOrFail()->status->value);

        $stranger = $this->createMember();
        $foreignStaff = StaffMember::factory()->forOrganization($stranger->organization)->linkedTo($stranger)->create(['last_name' => 'Foreignonly']);
        $foreignLocation = Location::factory()->forOrganization($stranger->organization)->create();
        $foreignToken = $this->openSession($this->createOrganizationAdmin($stranger->organization), $foreignLocation);

        $colleague = $this->createMember($admin->organization);
        StaffMember::factory()->forOrganization($admin->organization)->linkedTo($colleague)->create();
        $this->actingAs($colleague)
            ->post(route('org.attendance.check-in'), ['methods' => ['qr'], 'token' => $foreignToken])
            ->assertSessionHasErrors('attendance');
        $this->assertNull(AttendanceRecord::query()->where('staff_member_id', $colleague->staffMember->getKey())->whereNotNull('check_in_at')->value('id'));
        $this->actingAs($admin)->get(route('org.attendance.index'))->assertOk()->assertDontSee('Foreignonly');
    }

    #[Test]
    public function gps_ble_and_wifi_follow_the_verification_rules(): void
    {
        [$admin, $member, $staff, $location] = $this->office(geofence: true);

        $inside = $this->gps(5.6037, -0.1870, 20);
        $this->actingAs($member)
            ->post(route('org.attendance.check-in'), ['methods' => ['gps'], 'location_uuid' => $location->uuid] + $inside)
            ->assertSessionHas('status');
        $this->assertSame('present', AttendanceRecord::query()->where('staff_member_id', $staff->getKey())->firstOrFail()->status->value);

        $other = $this->createMember($admin->organization);
        $otherStaff = StaffMember::factory()->forOrganization($admin->organization)->linkedTo($other)->create(['primary_location_id' => $location->getKey()]);

        $this->actingAs($other)
            ->post(route('org.attendance.check-in'), ['methods' => ['gps'], 'location_uuid' => $location->uuid] + $this->gps(6.6885, -1.6244, 20))
            ->assertSessionHasErrors('attendance');

        $rejectedGps = AttendanceEvent::query()->where('staff_member_id', $otherStaff->getKey())->where('reason', 'outside_geofence')->first();
        $this->assertNotNull($rejectedGps);
        $this->assertNull($rejectedGps->attendance_record_id);
        $this->assertSame('outside', $rejectedGps->evidence['geofence_result']);
        $this->assertSame('rejected', $rejectedGps->evidence['validation_result']);
        $this->assertSame($location->getKey(), $rejectedGps->evidence['location_id']);
        $this->assertNotNull($rejectedGps->evidence['captured_at']);

        $acceptedGps = AttendanceEvent::query()->where('staff_member_id', $staff->getKey())->where('reason', 'inside_geofence')->first();
        $this->assertSame('inside', $acceptedGps->evidence['geofence_result']);
        $this->assertSame($location->getKey(), $acceptedGps->evidence['location_id']);
        $this->assertNotNull($acceptedGps->attendance_record_id);

        $this->actingAs($other)
            ->post(route('org.attendance.check-in'), ['methods' => ['gps'], 'location_uuid' => $location->uuid] + $this->gps(5.6037, -0.1870, 800))
            ->assertSessionHasErrors('attendance');
        $this->assertNull(AttendanceRecord::query()->where('staff_member_id', $otherStaff->getKey())->whereNotNull('check_in_at')->value('id'));

        $beaconMember = $this->createMember($admin->organization);
        StaffMember::factory()->forOrganization($admin->organization)->linkedTo($beaconMember)->create();
        Beacon::query()->create([
            'organization_id' => $admin->organization_id,
            'location_id' => $location->getKey(),
            'name' => 'Front door',
            'public_id' => 'beacon-front-door',
            'is_active' => true,
        ]);
        $this->actingAs($beaconMember)
            ->post(route('org.attendance.check-in'), ['methods' => ['ble'], 'beacon_public_id' => 'beacon-front-door'])
            ->assertSessionHas('status');
        $beaconEvent = AttendanceEvent::query()->where('reason', 'beacon_registered')->first();
        $this->assertSame('registered_identifier', $beaconEvent->evidence['evidence_kind']);
        $this->assertFalse($beaconEvent->evidence['proximity_verified']);

        $wifiMember = $this->createMember($admin->organization);
        StaffMember::factory()->forOrganization($admin->organization)->linkedTo($wifiMember)->create();
        WifiNetwork::query()->create([
            'organization_id' => $admin->organization_id,
            'location_id' => $location->getKey(),
            'label' => 'Office',
            'bssid' => 'AA:BB:CC:DD:EE:FF',
            'is_active' => true,
        ]);
        $this->actingAs($wifiMember)
            ->get(route('org.attendance.mine'))
            ->assertOk()
            ->assertSee('Front door')
            ->assertSee('Submit Front door')
            ->assertSee('Beacon evidence')
            ->assertSee('cannot detect a beacon automatically')
            ->assertDontSee('Check in at Front door')
            ->assertSee('Office')
            ->assertSee('A browser cannot read the network address')
            ->assertDontSee('AA:BB:CC:DD:EE:FF');
        $this->actingAs($wifiMember)
            ->post(route('org.attendance.check-in'), ['methods' => ['wifi'], 'bssid' => 'aa-bb-cc-dd-ee-ff'])
            ->assertSessionHasErrors('attendance');

        $token = $this->openSession($admin, $location);
        $this->actingAs($wifiMember)
            ->post(route('org.attendance.check-in'), ['methods' => ['qr', 'wifi'], 'token' => $token, 'bssid' => 'AA:BB:CC:DD:EE:FF'])
            ->assertSessionHas('status');

        $faceMember = $this->createMember($admin->organization);
        StaffMember::factory()->forOrganization($admin->organization)->linkedTo($faceMember)->create();
        $this->actingAs($faceMember)
            ->post(route('org.attendance.check-in'), ['methods' => ['face']])
            ->assertSessionHasErrors('attendance');
    }

    #[Test]
    public function field_work_requires_a_usable_location_reading(): void
    {
        [$admin, $member, , $location] = $this->office(geofence: true);
        $member->staffMember->update(['work_type' => WorkType::Field]);

        $this->actingAs($admin)->post(route('org.field.store'), [
            'staff_uuid' => $member->staffMember->uuid,
            'location_uuid' => $location->uuid,
            'title' => 'Inspection',
            'starts_on' => '2026-11-04',
            'ends_on' => '2026-11-04',
        ])->assertSessionHas('status');

        $assignment = FieldAssignment::query()->firstOrFail();

        $this->actingAs($member)->post(route('org.field.start', $assignment), $this->gps(5.6037, -0.1870, 900))
            ->assertSessionHasErrors('attendance');
        $this->assertDatabaseCount('field_activities', 0);

        $this->actingAs($member)->post(route('org.field.start', $assignment), $this->gps(5.6037, -0.1870, 25))
            ->assertSessionHas('status');
        $this->assertDatabaseCount('field_activities', 1);
    }

    #[Test]
    public function schedules_reject_a_second_assignment_and_approved_leave_covers_attendance(): void
    {
        [$admin, $member, $staff] = array_slice($this->office(), 0, 3);
        $manager = $this->createMember($admin->organization, role: 'organization_manager');

        $this->actingAs($admin)->post(route('org.schedules.store'), [
            'name' => 'Morning duty',
            'starts_at' => '08:00',
            'ends_at' => '16:00',
            'effective_from' => '2026-11-01',
            'effective_until' => '2026-12-01',
        ])->assertSessionHas('status');

        $shift = Shift::query()->firstOrFail();
        $this->actingAs($admin)->post(route('org.schedules.publish', $shift))->assertSessionHas('status');
        $this->actingAs($admin)->post(route('org.schedules.assign', $shift), [
            'staff_uuid' => $staff->uuid,
            'work_date' => '2026-11-06',
        ])->assertSessionHas('status');
        $this->actingAs($admin)->post(route('org.schedules.assign', $shift), [
            'staff_uuid' => $staff->uuid,
            'work_date' => '2026-11-06',
        ])->assertSessionHasErrors('schedule');

        $this->actingAs($admin)->post(route('org.leave.types.store'), [
            'name' => 'Annual',
            'code' => 'annual',
            'paid' => '1',
        ])->assertSessionHas('status');

        $type = LeaveType::query()->firstOrFail();
        $this->assertSame(0, $type->default_days);
        $this->actingAs($member)->post(route('org.leave.store'), [
            'leave_type_uuid' => $type->uuid,
            'starts_on' => '2026-11-10',
            'ends_on' => '2026-11-11',
            'reason' => 'Family travel',
        ])->assertSessionHas('status');

        $request = LeaveRequest::query()->firstOrFail();
        $this->assertSame('submitted', $request->status->value);

        $this->actingAs($member)->post(route('org.leave.decide', $request), [
            'decision' => 'approve',
            'decision_note' => 'No',
        ])->assertForbidden();

        $this->actingAs($manager)->post(route('org.leave.decide', $request), [
            'decision' => 'approve',
            'decision_note' => 'Granted',
        ])->assertSessionHas('status');

        $this->assertSame('approved', $request->fresh()->status->value);
        $this->assertSame(2, AttendanceRecord::query()->where('staff_member_id', $staff->getKey())->where('status', 'on_leave')->count());

        $this->actingAs($admin)->get(route('org.analytics.index'))
            ->assertOk()
            ->assertSee('Present today')
            ->assertSee('Status share')
            ->assertSee('Status counts')
            ->assertSee('Pending verification')
            ->assertSee('Verification methods')
            ->assertSee('Dynamic QR');
        $this->actingAs($admin)->get(route('org.analytics.export'))->assertOk();

        $stranger = $this->createOrganizationAdmin();
        $this->actingAs($stranger)->post(route('org.leave.decide', $request), [
            'decision' => 'reject',
            'decision_note' => 'No',
        ])->assertNotFound();
    }

    #[Test]
    public function validation_reserved_methods_corrections_and_overlapping_leave_fail_closed(): void
    {
        [$admin, $member, $staff] = array_slice($this->office(), 0, 3);

        $this->actingAs($member)->post(route('org.attendance.check-in'), [])->assertSessionHasErrors('methods');
        $this->actingAs($member)
            ->post(route('org.attendance.check-in'), ['methods' => ['fingerprint']])
            ->assertSessionHasErrors('attendance');
        $this->actingAs($member)
            ->post(route('org.attendance.check-in'), ['methods' => ['nfc']])
            ->assertSessionHasErrors('attendance');
        $this->assertDatabaseCount('attendance_records', 0);

        $this->actingAs($member)->post(route('org.attendance.corrections'), [
            'staff_uuid' => $staff->uuid,
            'work_date' => '2026-11-03',
            'status' => 'excused',
            'note' => 'Should not be allowed',
        ])->assertForbidden();

        $this->actingAs($admin)->post(route('org.attendance.corrections'), [
            'staff_uuid' => $staff->uuid,
            'work_date' => '2026-11-03',
            'status' => 'excused',
            'note' => 'Authorised absence',
        ])->assertSessionHas('status');
        $this->assertSame('excused', AttendanceRecord::query()->whereDate('work_date', '2026-11-03')->firstOrFail()->status->value);

        $this->actingAs($member)->post(route('org.attendance.sessions.store'), [
            'name' => 'Not allowed',
            'starts_at' => now()->subHour()->format('Y-m-d\TH:i'),
            'ends_at' => now()->addHour()->format('Y-m-d\TH:i'),
        ])->assertForbidden();

        $this->actingAs($admin)->post(route('org.leave.types.store'), [
            'name' => 'Annual',
            'code' => 'annual',
        ])->assertSessionHas('status');
        $type = LeaveType::query()->firstOrFail();

        $this->actingAs($member)->post(route('org.leave.store'), [
            'leave_type_uuid' => $type->uuid,
            'starts_on' => '2026-12-01',
            'ends_on' => '2026-12-03',
            'reason' => 'Travel',
        ])->assertSessionHas('status');

        $this->actingAs($member)->post(route('org.leave.store'), [
            'leave_type_uuid' => $type->uuid,
            'starts_on' => '2026-12-02',
            'ends_on' => '2026-12-04',
            'reason' => 'More travel',
        ])->assertSessionHasErrors('leave');

        $this->actingAs($member)->get(route('org.leave.index'))
            ->assertOk()
            ->assertSee('Request leave')
            ->assertSee('Pending')
            ->assertSee('Due')
            ->assertSee('Approved')
            ->assertSee('Travel');

        $pending = LeaveRequest::query()->firstOrFail();
        $manager = $this->createMember($admin->organization, role: 'organization_manager');
        $this->actingAs($manager)->post(route('org.leave.decide', $pending), [
            'decision' => 'reject',
        ])->assertSessionHasErrors('decision_note');
        $this->assertSame('submitted', $pending->fresh()->status->value);
    }

    #[Test]
    public function a_leave_type_with_no_remaining_days_cannot_be_requested(): void
    {
        [$admin, $member] = array_slice($this->office(), 0, 2);

        $this->actingAs($admin)->post(route('org.leave.types.store'), [
            'name' => 'Annual',
            'code' => 'annual',
            'requires_balance' => '1',
            'default_days' => '0',
        ])->assertSessionHas('status');

        $type = LeaveType::query()->firstOrFail();

        $this->actingAs($member)->get(route('org.leave.index'))
            ->assertOk()
            ->assertDontSee('Request leave')
            ->assertSee('No days remaining');

        $this->actingAs($member)->post(route('org.leave.store'), [
            'leave_type_uuid' => $type->uuid,
            'starts_on' => '2026-11-20',
            'ends_on' => '2026-11-20',
            'reason' => 'None left',
        ])->assertSessionHasErrors('leave');

        $this->assertDatabaseCount('leave_requests', 0);

        LeaveBalance::query()->where('leave_type_id', $type->getKey())->delete();
        $type->update(['default_days' => 2]);

        $this->actingAs($member)->post(route('org.leave.store'), [
            'leave_type_uuid' => $type->uuid,
            'starts_on' => '2026-11-20',
            'ends_on' => '2026-11-21',
            'reason' => 'Two days away',
        ])->assertSessionHas('status');

        $this->actingAs($member)->get(route('org.leave.index'))
            ->assertOk()
            ->assertSee('Pending')
            ->assertSee('Two days away')
            ->assertSee('2 days remaining');
    }

    #[Test]
    public function an_approver_can_see_and_edit_leave_types_after_they_are_created(): void
    {
        [$admin, $member] = array_slice($this->office(), 0, 2);

        $this->actingAs($admin)->post(route('org.leave.types.store'), [
            'name' => 'Compassionate',
            'code' => 'comp',
            'paid' => '1',
            'default_days' => '3',
        ])->assertSessionHas('status');

        $type = LeaveType::query()->firstOrFail();

        $this->actingAs($admin)->get(route('org.leave.index'))
            ->assertOk()
            ->assertSee('Leave types')
            ->assertSee('Compassionate')
            ->assertSee('COMP')
            ->assertSee('Edit Compassionate');

        $this->actingAs($member)->get(route('org.leave.index'))
            ->assertOk()
            ->assertDontSee('New leave type');

        $this->actingAs($member)->put(route('org.leave.types.update', $type), [
            'name' => 'Should fail',
            'code' => 'COMP',
        ])->assertForbidden();

        $this->actingAs($admin)->put(route('org.leave.types.update', $type), [
            'name' => 'Compassionate leave',
            'code' => 'comp',
            'paid' => '1',
            'is_active' => '0',
        ])->assertRedirect(route('org.leave.index'));

        $type->refresh();
        $this->assertSame('Compassionate leave', $type->name);
        $this->assertSame(3, $type->default_days);
        $this->assertFalse($type->is_active);

        $this->actingAs($member)->get(route('org.leave.index'))
            ->assertOk()
            ->assertDontSee('Compassionate leave');

        $stranger = $this->createOrganizationAdmin();
        $this->actingAs($stranger)->put(route('org.leave.types.update', $type), [
            'name' => 'Taken',
            'code' => 'COMP',
        ])->assertNotFound();
    }

    #[Test]
    public function a_night_shift_otp_device_binding_and_early_departure_follow_policy(): void
    {
        [$admin, $member, $staff, $location] = $this->office();

        $this->actingAs($admin)->post(route('org.schedules.store'), [
            'name' => 'Night duty',
            'starts_at' => '22:00',
            'ends_at' => '06:00',
            'effective_from' => '2026-11-01',
            'effective_until' => '2026-12-01',
        ])->assertSessionHas('status');

        $shift = Shift::query()->firstOrFail();
        $this->assertTrue($shift->crosses_midnight);
        $this->actingAs($admin)->post(route('org.schedules.publish', $shift))->assertSessionHas('status');
        $this->actingAs($admin)->post(route('org.schedules.assign', $shift), [
            'staff_uuid' => $staff->uuid,
            'work_date' => '2026-11-04',
        ])->assertSessionHas('status');

        Carbon::setTestNow(Carbon::parse('2026-11-04 22:05:00', 'Africa/Accra'));
        $token = $this->openSession($admin, $location);
        $this->actingAs($member)
            ->post(route('org.attendance.check-in'), ['methods' => ['qr'], 'token' => $token])
            ->assertSessionHas('status');
        $record = AttendanceRecord::query()->where('staff_member_id', $staff->getKey())->firstOrFail();
        $this->assertSame('present', $record->status->value);

        Carbon::setTestNow(Carbon::parse('2026-11-04 23:30:00', 'Africa/Accra'));
        $this->actingAs($member)->post(route('org.attendance.check-out'))->assertSessionHas('status');
        $this->assertGreaterThan(0, $record->fresh()->early_leave_minutes);

        $next = $this->createMember($admin->organization);
        $nextStaff = StaffMember::factory()->forOrganization($admin->organization)->linkedTo($next)->create([
            'primary_location_id' => $location->getKey(),
        ]);
        WorkforcePolicy::resolve($admin->organization)->update(['otp_enabled' => false]);
        $this->actingAs($admin)->post(route('org.attendance.otps.store'), [
            'staff_uuid' => $nextStaff->uuid,
        ])->assertSessionHasErrors('otp');

        WorkforcePolicy::resolve($admin->organization)->update(['otp_enabled' => true, 'require_device_binding' => true]);
        AttendanceOtp::query()->create([
            'organization_id' => $admin->organization_id,
            'staff_member_id' => $nextStaff->getKey(),
            'issued_by' => $admin->getKey(),
            'code_hash' => hash('sha256', '654321'),
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->actingAs($next)
            ->post(route('org.attendance.check-in'), ['methods' => ['otp'], 'otp' => '654321'])
            ->assertSessionHasErrors('attendance');

        $device = str_repeat('ab', 16);
        $otpToken = $this->openSession($admin, $location);
        $this->actingAs($next)->post(route('org.attendance.check-in'), [
            'methods' => ['qr', 'device'],
            'token' => $otpToken,
            'device_hash' => $device,
        ])->assertSessionHas('status');
        $this->assertSame(1, BoundDevice::query()->where('user_id', $next->getKey())->count());

        $this->actingAs($admin)->post(route('org.attendance.wifi.store'), [
            'label' => 'Lab',
            'bssid' => 'not-a-mac',
            'location_uuid' => $location->uuid,
        ])->assertSessionHasErrors('bssid');
    }

    #[Test]
    public function missing_check_out_schedule_reminders_and_quiet_field_work_are_handled(): void
    {
        [$admin, $member, $staff, $location] = $this->office(geofence: true);
        $member->staffMember->update(['work_type' => WorkType::Field]);

        AttendanceRecord::query()->create([
            'organization_id' => $admin->organization_id,
            'staff_member_id' => $staff->getKey(),
            'user_id' => $member->getKey(),
            'work_date' => '2026-11-03',
            'check_in_at' => Carbon::parse('2026-11-03 08:10:00', 'Africa/Accra'),
            'status' => 'present',
            'source' => 'self',
        ]);

        $this->artisan('attendance:close-open')->assertSuccessful();
        $this->assertSame('incomplete', AttendanceRecord::query()->whereDate('work_date', '2026-11-03')->firstOrFail()->status->value);
        $this->assertTrue($member->fresh()->notifications->contains(
            fn ($notification) => ($notification->data['event'] ?? null) === 'attendance.missing_checkout',
        ));

        $this->actingAs($admin)->post(route('org.schedules.store'), [
            'name' => 'Day duty',
            'starts_at' => '08:00',
            'ends_at' => '17:00',
            'effective_from' => '2026-11-01',
        ])->assertSessionHas('status');
        $shift = Shift::query()->firstOrFail();
        $this->actingAs($admin)->post(route('org.schedules.publish', $shift));
        $this->actingAs($admin)->post(route('org.schedules.assign', $shift), [
            'staff_uuid' => $staff->uuid,
            'work_date' => '2026-11-05',
        ])->assertSessionHas('status');
        $this->assertSame(1, ShiftAssignment::query()->count());

        $this->artisan('workforce:remind')->assertSuccessful();
        $this->assertTrue($member->fresh()->notifications->contains(
            fn ($notification) => ($notification->data['event'] ?? null) === 'schedule.reminder',
        ));

        $this->actingAs($admin)->post(route('org.field.store'), [
            'staff_uuid' => $staff->uuid,
            'location_uuid' => $location->uuid,
            'title' => 'Inspection',
            'starts_on' => '2026-11-04',
            'ends_on' => '2026-11-04',
        ])->assertSessionHas('status');
        $assignment = FieldAssignment::query()->firstOrFail();
        $this->actingAs($member)->post(route('org.field.start', $assignment), $this->gps(5.6037, -0.1870, 20))
            ->assertSessionHas('status');

        Carbon::setTestNow(now()->addHours(2));
        $this->artisan('workforce:field-status')->assertSuccessful();
        $this->assertSame('delayed', FieldActivity::query()->firstOrFail()->status->value);

        $this->actingAs($admin)->put(route('org.notifications.update'), [
            'events' => [
                'attendance.confirmed' => ['mail' => '1', 'database' => '1'],
            ],
        ])->assertSessionHas('status');
        $this->assertDatabaseHas('notification_preferences', [
            'organization_id' => $admin->organization_id,
            'event' => 'field.alert',
            'sms' => 0,
            'push' => 0,
        ]);
    }

    #[Test]
    public function opening_a_session_tells_people_who_can_check_in_and_the_code_stays_scannable(): void
    {
        Notification::fake();
        [$admin, $member, $staff, $location] = $this->office();
        $outsider = $this->createMember();
        $suspended = $this->createMember($admin->organization, attributes: ['status' => UserStatus::Suspended]);

        $this->actingAs($admin)->post(route('org.attendance.sessions.store'), [
            'name' => 'Morning register',
            'location_uuid' => $location->uuid,
            'starts_at' => now()->subHour()->format('Y-m-d\TH:i'),
            'ends_at' => now()->addHours(4)->format('Y-m-d\TH:i'),
            'qr_ttl_seconds' => 60,
        ])->assertRedirect()->assertSessionHas('status');

        Notification::assertSentTo($member, WorkforceMessage::class, function (WorkforceMessage $message) use ($location, $admin, $member): bool {
            $html = (string) $message->toMail($member)->render();

            return $message->event === 'session.opened'
                && str_contains($message->title, 'Morning register')
                && str_contains($message->body, $location->name)
                && str_contains($message->body, '/org/attendance/mine#opened-sessions')
                && str_contains($message->body, 'does not include it')
                && str_contains((string) $message->actionUrl, '/org/attendance/mine#opened-sessions')
                && str_contains((string) $message->actionUrl, $admin->organization->primaryHost())
                && str_contains($html, '/org/attendance/mine#opened-sessions');
        });
        Notification::assertSentTo($admin, WorkforceMessage::class, fn (WorkforceMessage $message): bool => $message->event === 'session.opened');
        Notification::assertNotSentTo($outsider, WorkforceMessage::class);
        Notification::assertNotSentTo($suspended, WorkforceMessage::class);

        $session = AttendanceSession::query()->firstOrFail();
        $this->actingAs($admin)
            ->get(route('org.attendance.sessions.show', $session))
            ->assertOk()
            ->assertSee('Scan to check in')
            ->assertSee('width:18rem;height:18rem', false);

        $this->assertStringContainsString('width="100%"', QrSvg::render('https://example.test/org/attendance/arrive/'.str_repeat('a', 48)));

        $this->actingAs($member)
            ->get(route('org.attendance.mine'))
            ->assertOk()
            ->assertSee('id="opened-sessions"', false)
            ->assertSee('Morning register')
            ->assertSee($location->name)
            ->assertSee('Check in with this code')
            ->assertSee('width:18rem;height:18rem', false);

        $pageToken = app(QrSessionService::class)->issue($session);
        $this->actingAs($member)
            ->postJson(route('org.attendance.sessions.code', $session), ['previous' => $pageToken])
            ->assertOk()
            ->assertJsonStructure(['token', 'svg', 'expires_in']);
        $this->actingAs($member)
            ->post(route('org.attendance.check-in'), ['methods' => ['qr'], 'token' => $pageToken])
            ->assertSessionHasErrors('attendance');

        $kept = app(QrSessionService::class)->issue($session);
        app(QrSessionService::class)->issue($session);
        $this->assertNotNull(
            AttendanceToken::query()->where('token_hash', hash('sha256', $kept))->where('expires_at', '>', now())->first()
        );

        $token = app(QrSessionService::class)->issue($session);
        $url = app(QrSessionService::class)->checkInUrl($session, $token);

        $this->actingAs($member)
            ->get(route('org.attendance.arrive', ['token' => $token]))
            ->assertOk()
            ->assertSee('Check in now')
            ->assertSee('Morning register');

        $colleague = $this->createMember($admin->organization);
        StaffMember::factory()->forOrganization($admin->organization)->linkedTo($colleague)->create([
            'primary_location_id' => $location->getKey(),
            'work_type' => WorkType::Office,
        ]);

        $this->actingAs($colleague)
            ->post(route('org.attendance.check-in'), [
                'methods' => ['qr'],
                'token' => $url,
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->actingAs($member)
            ->post(route('org.attendance.arrive.store', ['token' => $token]))
            ->assertRedirect(route('org.attendance.mine'))
            ->assertSessionHas('status');

        $fresh = app(QrSessionService::class)->issue($session);
        $this->actingAs($member)
            ->post(route('org.attendance.arrive.store', ['token' => $fresh]))
            ->assertSessionHasErrors('attendance');

        $this->assertSame('present', AttendanceRecord::query()->where('staff_member_id', $staff->getKey())->firstOrFail()->status->value);

        $this->actingAs($member)
            ->get(route('org.attendance.arrive', ['token' => str_repeat('z', 48)]))
            ->assertOk()
            ->assertSee('This code is no longer valid');

        $this->assertStringContainsString('/org/attendance/arrive/'.$token, $url);
    }

    #[Test]
    public function a_current_code_serves_every_eligible_person_until_it_expires(): void
    {
        [$admin, $member, $staff, $location] = $this->office(geofence: true);
        $token = $this->openSession($admin, $location);
        $first = AttendanceSession::query()->latest('id')->firstOrFail();

        $colleague = $this->createMember($admin->organization);
        $colleagueStaff = StaffMember::factory()->forOrganization($admin->organization)->linkedTo($colleague)->create([
            'primary_location_id' => $location->getKey(),
            'work_type' => WorkType::Office,
        ]);

        $this->actingAs($member)
            ->post(route('org.attendance.check-in'), ['methods' => ['gps'], 'location_uuid' => $location->uuid] + $this->gps(5.6037, -0.1870, 15))
            ->assertSessionHas('status');

        $this->actingAs($member)
            ->post(route('org.attendance.check-in'), ['methods' => ['qr'], 'token' => $token])
            ->assertSessionHas('status');
        $this->actingAs($colleague)
            ->post(route('org.attendance.check-in'), ['methods' => ['qr'], 'token' => $token])
            ->assertSessionHas('status');

        $this->assertSame(2, AttendanceRecord::query()->where('attendance_session_id', $first->getKey())->count());
        $this->assertSame(2, AttendanceRecord::query()->where('staff_member_id', $staff->getKey())->count());
        $this->assertTrue(
            AttendanceToken::query()->where('token_hash', hash('sha256', $token))->where('expires_at', '>', now())->exists()
        );
        $this->assertStringNotContainsString($token, (string) json_encode(AttendanceEvent::query()->pluck('evidence')));

        $this->actingAs($member)
            ->post(route('org.attendance.check-in'), ['methods' => ['qr'], 'token' => $token])
            ->assertSessionHasErrors(['attendance' => 'Attendance is already recorded for this session.']);
        $this->actingAs($member)
            ->post(route('org.attendance.check-in'), ['methods' => ['gps'], 'location_uuid' => $location->uuid] + $this->gps(5.6037, -0.1870, 15))
            ->assertSessionHasErrors(['attendance' => 'Attendance is already recorded for this work period.']);

        $rotated = app(QrSessionService::class)->issue($first, $token);
        $newcomer = $this->createMember($admin->organization);
        StaffMember::factory()->forOrganization($admin->organization)->linkedTo($newcomer)->create([
            'primary_location_id' => $location->getKey(),
            'work_type' => WorkType::Office,
        ]);
        $this->actingAs($newcomer)
            ->post(route('org.attendance.check-in'), ['methods' => ['qr'], 'token' => $token])
            ->assertSessionHasErrors(['attendance' => 'This attendance code has expired.']);
        $this->actingAs($newcomer)
            ->post(route('org.attendance.check-in'), ['methods' => ['qr'], 'token' => 'not-a-real-code'])
            ->assertSessionHasErrors(['attendance' => 'This attendance code is not valid.']);
        $this->actingAs($newcomer)
            ->post(route('org.attendance.check-in'), ['methods' => ['qr'], 'token' => $rotated])
            ->assertSessionHas('status');

        $secondToken = $this->openSession($admin, $location);
        $second = AttendanceSession::query()->latest('id')->firstOrFail();
        $this->actingAs($member)
            ->post(route('org.attendance.check-in'), ['methods' => ['qr'], 'token' => $secondToken])
            ->assertSessionHas('status');
        $this->assertSame(3, AttendanceRecord::query()->where('staff_member_id', $staff->getKey())->count());

        $sessionRecord = AttendanceRecord::query()
            ->where('staff_member_id', $staff->getKey())
            ->where('attendance_session_id', $first->getKey())
            ->firstOrFail();
        $this->actingAs($member)
            ->post(route('org.attendance.check-out'), ['record' => $sessionRecord->uuid])
            ->assertSessionHas('status');
        $this->assertNotNull($sessionRecord->fresh()->check_out_at);
        $this->assertNull(
            AttendanceRecord::query()->where('staff_member_id', $staff->getKey())->where('attendance_session_id', $second->getKey())->firstOrFail()->check_out_at
        );
        $this->assertNull(
            AttendanceRecord::query()->where('staff_member_id', $staff->getKey())->where('period_key', AttendanceRecord::dayPeriod(now($admin->organization->timezone ?: 'UTC')->toDateString()))->firstOrFail()->check_out_at
        );

        $unlinked = $this->createMember($admin->organization);
        $this->actingAs($unlinked)
            ->get(route('org.attendance.mine'))
            ->assertOk()
            ->assertSee('No person record is linked to this account')
            ->assertDontSee('No staff record linked');

        $this->actingAs($admin)
            ->get(route('org.attendance.sessions'))
            ->assertOk()
            ->assertSee('does not prove they are standing next to it')
            ->assertDontSee('near a registered device');

        $this->assertSame($colleagueStaff->getKey(), AttendanceRecord::query()->where('attendance_session_id', $first->getKey())->where('staff_member_id', $colleagueStaff->getKey())->value('staff_member_id'));
    }

    /**
     * @return array{0: User, 1: User, 2: StaffMember, 3: Location}
     */
    private function office(bool $geofence = false): array
    {
        $admin = $this->createOrganizationAdmin();
        $member = $this->createMember($admin->organization);
        $location = Location::factory()->forOrganization($admin->organization)->create($geofence ? [
            'latitude' => 5.6037,
            'longitude' => -0.1870,
            'geofence_radius_m' => 200,
        ] : []);
        $staff = StaffMember::factory()->forOrganization($admin->organization)->linkedTo($member)->create([
            'primary_location_id' => $location->getKey(),
            'work_type' => WorkType::Office,
        ]);

        return [$admin, $member, $staff, $location];
    }

    private function openSession(User $admin, Location $location, int $ttl = 60): string
    {
        $this->actingAs($admin)->post(route('org.attendance.sessions.store'), [
            'name' => 'Morning register',
            'location_uuid' => $location->uuid,
            'starts_at' => now()->subHour()->format('Y-m-d\TH:i'),
            'ends_at' => now()->addHours(4)->format('Y-m-d\TH:i'),
            'qr_ttl_seconds' => $ttl,
        ])->assertRedirect();

        $session = AttendanceSession::query()->latest('id')->firstOrFail();

        return app(QrSessionService::class)->issue($session);
    }

    /**
     * @return array<string, mixed>
     */
    private function gps(float $lat, float $lng, int $accuracy): array
    {
        return [
            'latitude' => $lat,
            'longitude' => $lng,
            'accuracy_m' => $accuracy,
            'captured_at' => now()->toIso8601String(),
        ];
    }
}
