<?php

namespace Tests\Feature\Reports;

use App\Enums\AttendanceStatus;
use App\Enums\LeaveStatus;
use App\Jobs\GenerateReportExport;
use App\Models\AttendanceRecord;
use App\Models\Department;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\ReportExport;
use App\Models\StaffMember;
use App\Models\User;
use App\Reports\Contracts\ReportProvider;
use App\Reports\Export\ExportManager;
use App\Reports\ReportDefinition;
use App\Reports\ReportFilters;
use App\Reports\ReportFormat;
use App\Reports\ReportRegistry;
use App\Reports\ReportResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReportsAndExportsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function an_organisation_admin_can_open_the_reports_catalog_and_a_summary(): void
    {
        $admin = $this->createOrganizationAdmin();
        $person = StaffMember::factory()->forOrganization($admin->organization)->create();
        $today = now($admin->organization->timezone)->toDateString();
        $this->record($person, $today, AttendanceStatus::Present);

        $this->actingAs($admin)
            ->get(route('org.reports.index'))
            ->assertOk()
            ->assertSee('Attendance summary')
            ->assertSee('Attendance detail');

        $this->actingAs($admin)
            ->get(route('org.reports.show', 'attendance-summary'))
            ->assertOk()
            ->assertSee('Attendance summary')
            ->assertSee('Present today');
    }

    #[Test]
    public function a_member_without_analytics_cannot_open_reports_or_exports(): void
    {
        $admin = $this->createOrganizationAdmin();
        $member = $this->createMember($admin->organization);

        $this->actingAs($member)->get(route('org.reports.index'))->assertForbidden();
        $this->actingAs($member)->get(route('org.reports.show', 'attendance-detail'))->assertForbidden();
        $this->actingAs($member)->get(route('org.reports.export', [
            'report' => 'attendance-detail',
            'format' => 'csv',
        ]))->assertForbidden();
    }

    #[Test]
    public function department_heads_cannot_widen_scope_with_forged_filters(): void
    {
        $admin = $this->createOrganizationAdmin();
        $today = now($admin->organization->timezone)->toDateString();
        $operations = Department::factory()->create(['organization_id' => $admin->organization_id, 'name' => 'Operations']);
        $finance = Department::factory()->create(['organization_id' => $admin->organization_id, 'name' => 'Finance']);
        $manager = $this->createMember($admin->organization, 'organization_manager');
        $head = StaffMember::factory()->forOrganization($admin->organization)->linkedTo($manager)->create([
            'department_id' => $operations->getKey(),
        ]);
        $operations->update(['head_staff_member_id' => $head->getKey()]);
        $visible = StaffMember::factory()->forOrganization($admin->organization)->create([
            'department_id' => $operations->getKey(),
            'first_name' => 'Visible',
            'last_name' => 'Officer',
        ]);
        $hidden = StaffMember::factory()->forOrganization($admin->organization)->create([
            'department_id' => $finance->getKey(),
            'first_name' => 'Hidden',
            'last_name' => 'Colleague',
            'staff_number' => 'HID-200',
        ]);
        $this->record($visible, $today, AttendanceStatus::Present);
        $this->record($hidden, $today, AttendanceStatus::Present);
        $manager->unsetRelation('staffMember');

        $this->actingAs($manager)
            ->get(route('org.reports.show', [
                'report' => 'attendance-by-person',
                'department' => $finance->uuid,
            ]))
            ->assertOk()
            ->assertSee('Visible Officer')
            ->assertDontSee('Hidden Colleague');

        $csv = $this->actingAs($manager)->get(route('org.reports.export', [
            'report' => 'attendance-detail',
            'format' => 'csv',
            'from' => $today,
            'to' => $today,
            'department' => $finance->uuid,
            'person' => $hidden->uuid,
        ]));
        $csv->assertOk();
        $this->assertStringNotContainsString('HID-200', $csv->streamedContent());
        $this->assertStringContainsString('Visible', $csv->streamedContent());
    }

    #[Test]
    public function tenant_isolation_blocks_cross_tenant_report_data(): void
    {
        $admin = $this->createOrganizationAdmin();
        $other = $this->createOrganizationAdmin();
        $today = now($admin->organization->timezone)->toDateString();
        $foreign = StaffMember::factory()->forOrganization($other->organization)->create([
            'first_name' => 'Foreign',
            'last_name' => 'Person',
            'staff_number' => 'FRN-1',
        ]);
        $this->record($foreign, $today, AttendanceStatus::Present);

        $this->actingAs($admin)
            ->get(route('org.reports.show', [
                'report' => 'attendance-detail',
                'person' => $foreign->uuid,
            ]))
            ->assertOk()
            ->assertDontSee('Foreign Person')
            ->assertDontSee('FRN-1');
    }

    #[Test]
    public function export_columns_match_the_on_screen_report_and_escape_formulas(): void
    {
        $admin = $this->createOrganizationAdmin();
        $today = now($admin->organization->timezone)->toDateString();
        $person = StaffMember::factory()->forOrganization($admin->organization)->create([
            'first_name' => '=Danger',
            'last_name' => 'Name',
            'staff_number' => '+123',
        ]);
        $this->record($person, $today, AttendanceStatus::Late);

        $html = $this->actingAs($admin)->get(route('org.reports.show', [
            'report' => 'attendance-detail',
            'from' => $today,
            'to' => $today,
        ]));
        $html->assertOk()->assertSee('=Danger Name');

        $csv = $this->actingAs($admin)->get(route('org.reports.export', [
            'report' => 'attendance-detail',
            'format' => 'csv',
            'from' => $today,
            'to' => $today,
        ]));
        $csv->assertOk();
        $content = $csv->streamedContent();
        $this->assertStringContainsString('Date', $content);
        $this->assertStringContainsString('Staff number', $content);
        $this->assertStringContainsString('Late minutes', $content);
        $this->assertStringContainsString("'+123", $content);
        $this->assertStringContainsString("'=Danger Name", $content);
    }

    #[Test]
    public function analytics_csv_alias_uses_the_shared_attendance_detail_exporter(): void
    {
        $admin = $this->createOrganizationAdmin();
        $today = now($admin->organization->timezone)->toDateString();
        $person = StaffMember::factory()->forOrganization($admin->organization)->create([
            'first_name' => 'Alias',
            'last_name' => 'Person',
        ]);
        $this->record($person, $today, AttendanceStatus::Present);

        $response = $this->actingAs($admin)->get(route('org.analytics.export', [
            'from' => $today,
            'to' => $today,
        ]));
        $response->assertOk();
        $this->assertStringContainsString('Alias Person', $response->streamedContent());
    }

    #[Test]
    public function xlsx_and_pdf_exports_are_authorized_and_non_empty(): void
    {
        $admin = $this->createOrganizationAdmin();
        $today = now($admin->organization->timezone)->toDateString();
        $person = StaffMember::factory()->forOrganization($admin->organization)->create();
        $this->record($person, $today, AttendanceStatus::Present);

        $this->actingAs($admin)
            ->get(route('org.reports.export', [
                'report' => 'attendance-summary',
                'format' => 'xlsx',
                'from' => $today,
                'to' => $today,
            ]))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->actingAs($admin)
            ->get(route('org.reports.export', [
                'report' => 'attendance-summary',
                'format' => 'pdf',
                'from' => $today,
                'to' => $today,
            ]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    #[Test]
    public function unsupported_pdf_for_detail_report_is_rejected(): void
    {
        $admin = $this->createOrganizationAdmin();

        $this->actingAs($admin)
            ->get(route('org.reports.export', [
                'report' => 'attendance-detail',
                'format' => 'pdf',
            ]))
            ->assertNotFound();
    }

    #[Test]
    public function leave_summary_respects_department_scope_and_date_bounds(): void
    {
        $admin = $this->createOrganizationAdmin();
        $operations = Department::factory()->create(['organization_id' => $admin->organization_id]);
        $finance = Department::factory()->create(['organization_id' => $admin->organization_id]);
        $manager = $this->createMember($admin->organization, 'organization_manager');
        $head = StaffMember::factory()->forOrganization($admin->organization)->linkedTo($manager)->create([
            'department_id' => $operations->getKey(),
        ]);
        $operations->update(['head_staff_member_id' => $head->getKey()]);
        $visible = StaffMember::factory()->forOrganization($admin->organization)->create([
            'department_id' => $operations->getKey(),
            'first_name' => 'Leave',
            'last_name' => 'Visible',
        ]);
        $hidden = StaffMember::factory()->forOrganization($admin->organization)->create([
            'department_id' => $finance->getKey(),
            'first_name' => 'Leave',
            'last_name' => 'Hidden',
        ]);
        $type = LeaveType::query()->create([
            'organization_id' => $admin->organization_id,
            'name' => 'Annual',
            'code' => 'ANNUAL',
            'paid' => true,
            'requires_balance' => true,
            'is_active' => true,
        ]);
        foreach ([$visible, $hidden] as $person) {
            LeaveRequest::query()->create([
                'organization_id' => $admin->organization_id,
                'staff_member_id' => $person->getKey(),
                'leave_type_id' => $type->getKey(),
                'status' => LeaveStatus::Approved->value,
                'starts_on' => '2026-11-10',
                'ends_on' => '2026-11-11',
                'day_count' => 2,
                'reason' => 'Family event',
            ]);
        }
        $manager->unsetRelation('staffMember');

        $this->actingAs($manager)
            ->get(route('org.reports.show', [
                'report' => 'leave-summary',
                'from' => '2026-11-01',
                'to' => '2026-11-30',
            ]))
            ->assertOk()
            ->assertSee('Leave Visible')
            ->assertDontSee('Leave Hidden');
    }

    #[Test]
    public function notification_delivery_report_is_organisation_perspective_only(): void
    {
        $admin = $this->createOrganizationAdmin();
        $manager = $this->createMember($admin->organization, 'organization_manager');
        $operations = Department::factory()->create(['organization_id' => $admin->organization_id]);
        $head = StaffMember::factory()->forOrganization($admin->organization)->linkedTo($manager)->create([
            'department_id' => $operations->getKey(),
        ]);
        $operations->update(['head_staff_member_id' => $head->getKey()]);
        $manager->unsetRelation('staffMember');

        $this->actingAs($admin)
            ->get(route('org.reports.show', 'notification-delivery'))
            ->assertOk()
            ->assertSee('Notification delivery');

        $this->actingAs($manager)
            ->get(route('org.reports.show', 'notification-delivery'))
            ->assertForbidden();
    }

    #[Test]
    public function platform_reports_exclude_tenant_member_names(): void
    {
        $platform = User::factory()->platform()->create();
        $platform->assignRole('platform_super_admin');
        $admin = $this->createOrganizationAdmin();
        StaffMember::factory()->forOrganization($admin->organization)->create([
            'first_name' => 'Secret',
            'last_name' => 'Member',
        ]);

        $this->actingAs($platform)
            ->get(route('admin.reports.index'))
            ->assertOk()
            ->assertSee('Platform organisation statistics');

        $this->actingAs($platform)
            ->get(route('admin.reports.show', 'platform-organisation-statistics'))
            ->assertOk()
            ->assertDontSee('Secret Member')
            ->assertSee('Organisations');
    }

    #[Test]
    public function platform_support_can_open_platform_reports(): void
    {
        $support = User::factory()->platform()->create();
        $support->assignRole('platform_support');

        $this->actingAs($support)
            ->get(route('admin.reports.index'))
            ->assertOk()
            ->assertSee('Platform organisation statistics');
    }

    #[Test]
    public function large_exports_are_queued_instead_of_blocking(): void
    {
        Queue::fake();
        $admin = $this->createOrganizationAdmin();
        $registry = app(ReportRegistry::class);
        $inner = $registry->get('attendance-detail');
        $filters = ReportFilters::fromInput($admin, [], $inner->definition()->filters);

        $stub = new class($inner) implements ReportProvider
        {
            public function __construct(private ReportProvider $inner) {}

            public function definition(): ReportDefinition
            {
                return $this->inner->definition();
            }

            public function authorize(User $user): bool
            {
                return $this->inner->authorize($user);
            }

            public function build(ReportFilters $filters, int $page = 1): ReportResult
            {
                return $this->inner->build($filters, $page);
            }

            public function exportRows(ReportFilters $filters): \Generator
            {
                yield from $this->inner->exportRows($filters);
            }

            public function estimateRows(ReportFilters $filters): int
            {
                return ExportManager::QUEUE_THRESHOLD + 1;
            }
        };

        $response = app(ExportManager::class)->download($stub, $filters, ReportFormat::Xlsx, $admin);
        $this->assertTrue($response->isRedirect());
        $this->assertDatabaseHas('report_exports', [
            'user_id' => $admin->getKey(),
            'report_key' => 'attendance-detail',
            'format' => 'xlsx',
            'status' => 'queued',
        ]);
        Queue::assertPushed(GenerateReportExport::class);
    }

    #[Test]
    public function queued_export_job_writes_a_downloadable_file(): void
    {
        Storage::fake('local');
        $admin = $this->createOrganizationAdmin();
        $today = now($admin->organization->timezone)->toDateString();
        $person = StaffMember::factory()->forOrganization($admin->organization)->create([
            'first_name' => 'Queued',
            'last_name' => 'Export',
        ]);
        $this->record($person, $today, AttendanceStatus::Present);

        $export = ReportExport::query()->create([
            'organization_id' => $admin->organization_id,
            'user_id' => $admin->getKey(),
            'report_key' => 'attendance-summary',
            'format' => 'csv',
            'filters' => ['from' => $today, 'to' => $today],
            'status' => 'queued',
            'row_estimate' => 1,
            'expires_at' => now()->addDay(),
        ]);

        (new GenerateReportExport($export->getKey(), (int) $admin->organization_id))->handle(
            app(ReportRegistry::class),
            app(ExportManager::class),
        );

        $export->refresh();
        $this->assertSame('ready', $export->status);
        $this->assertNotNull($export->disk_path);
        Storage::disk('local')->assertExists($export->disk_path);

        $this->actingAs($admin)
            ->get(route('org.reports.downloads.show', $export->uuid))
            ->assertOk();
    }

    #[Test]
    public function empty_datasets_render_an_accessible_empty_state(): void
    {
        $admin = $this->createOrganizationAdmin();

        $this->actingAs($admin)
            ->get(route('org.reports.show', [
                'report' => 'punctuality',
                'from' => '2026-01-01',
                'to' => '2026-01-07',
            ]))
            ->assertOk()
            ->assertSee('No late arrivals in this period.');
    }

    #[Test]
    public function half_open_date_bounds_exclude_the_following_day(): void
    {
        $admin = $this->createOrganizationAdmin();
        $person = StaffMember::factory()->forOrganization($admin->organization)->create([
            'first_name' => 'Boundary',
            'last_name' => 'Person',
        ]);
        $this->record($person, '2026-11-10', AttendanceStatus::Present);
        $this->record($person, '2026-11-11', AttendanceStatus::Late);

        $csv = $this->actingAs($admin)->get(route('org.reports.export', [
            'report' => 'attendance-detail',
            'format' => 'csv',
            'from' => '2026-11-10',
            'to' => '2026-11-10',
        ]));
        $csv->assertOk();
        $content = $csv->streamedContent();
        $this->assertStringContainsString('2026-11-10', $content);
        $this->assertStringContainsString('Present', $content);
        $this->assertStringNotContainsString('2026-11-11', $content);
        $this->assertDoesNotMatchRegularExpression('/,Late,/', $content);
    }

    private function record(StaffMember $person, string $date, AttendanceStatus $status): void
    {
        AttendanceRecord::query()->create([
            'organization_id' => $person->organization_id,
            'staff_member_id' => $person->getKey(),
            'department_id' => $person->department_id,
            'work_date' => $date,
            'period_key' => AttendanceRecord::dayPeriod($date),
            'status' => $status->value,
            'late_minutes' => $status === AttendanceStatus::Late ? 12 : 0,
        ]);
    }
}
