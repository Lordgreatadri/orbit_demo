<?php

namespace Tests\Feature\Reports;

use App\Analytics\AnalyticsScope;
use App\Analytics\OperationalDigest;
use App\Enums\AttendanceStatus;
use App\Enums\LeaveStatus;
use App\Enums\ShiftStatus;
use App\Jobs\GenerateReportExport;
use App\Models\AttendanceRecord;
use App\Models\Department;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\ReportExport;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\StaffMember;
use App\Models\User;
use App\Models\WorkArrangement;
use App\Reports\Contracts\ReportProvider;
use App\Reports\Export\ExportManager;
use App\Reports\ReportDefinition;
use App\Reports\ReportFilters;
use App\Reports\ReportFormat;
use App\Reports\ReportRegistry;
use App\Reports\ReportResult;
use App\Workforce\LeavePlanner;
use App\Workforce\WorkforceReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class ReportReviewFixesTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    #[Test]
    public function a_download_is_rejected_when_department_access_no_longer_covers_the_artifact(): void
    {
        Storage::fake('local');
        $admin = $this->createOrganizationAdmin();
        $operations = Department::factory()->create(['organization_id' => $admin->organization_id, 'name' => 'Operations']);
        $finance = Department::factory()->create(['organization_id' => $admin->organization_id, 'name' => 'Finance']);
        $manager = $this->createMember($admin->organization, 'organization_manager');
        $head = StaffMember::factory()->forOrganization($admin->organization)->linkedTo($manager)->create([
            'department_id' => $operations->getKey(),
        ]);
        $operations->update(['head_staff_member_id' => $head->getKey()]);
        $finance->update(['head_staff_member_id' => $head->getKey()]);
        $manager->unsetRelation('staffMember');

        $both = $this->readyExport($manager, [
            'organization_id' => $admin->organization_id,
            'perspective' => 'department',
            'department_ids' => [$operations->getKey(), $finance->getKey()],
            'staff_member_id' => null,
        ]);
        $operationsOnly = $this->readyExport($manager, [
            'organization_id' => $admin->organization_id,
            'perspective' => 'department',
            'department_ids' => [$operations->getKey()],
            'staff_member_id' => null,
        ]);

        $this->actingAs($manager)->get(route('org.reports.downloads.show', $both->uuid))->assertOk();

        $finance->update(['head_staff_member_id' => null]);
        $manager->unsetRelation('staffMember');

        $this->actingAs($manager)->get(route('org.reports.downloads.show', $both->uuid))->assertForbidden();
        $this->actingAs($manager)->get(route('org.reports.downloads.show', $operationsOnly->uuid))->assertOk();
    }

    #[Test]
    public function a_download_is_rejected_when_report_permission_is_removed(): void
    {
        Storage::fake('local');
        $admin = $this->createOrganizationAdmin();
        $export = $this->readyExport($admin, [
            'organization_id' => $admin->organization_id,
            'perspective' => 'organisation',
            'department_ids' => null,
            'staff_member_id' => null,
        ]);

        $admin->syncRoles(['member']);

        $this->actingAs($admin->fresh())->get(route('org.reports.downloads.show', $export->uuid))->assertForbidden();
    }

    #[Test]
    public function a_failed_export_job_rethrows_so_the_queue_can_retry(): void
    {
        $admin = $this->createOrganizationAdmin();
        $export = ReportExport::query()->create([
            'organization_id' => $admin->organization_id,
            'user_id' => $admin->getKey(),
            'report_key' => 'attendance-summary',
            'format' => 'csv',
            'filters' => [],
            'generation_scope' => [
                'organization_id' => $admin->organization_id,
                'perspective' => 'organisation',
                'department_ids' => null,
                'staff_member_id' => null,
            ],
            'status' => ReportExport::STATUS_QUEUED,
            'expires_at' => now()->addDay(),
        ]);

        $job = new class($export->getKey(), (int) $admin->organization_id) extends GenerateReportExport
        {
            protected function writeExport(
                ExportManager $exports,
                ReportProvider $provider,
                ReportFilters $filters,
                ReportFormat $format,
                string $absolute,
            ): void {
                throw new RuntimeException('storage unavailable');
            }
        };

        try {
            $job->handle(app(ReportRegistry::class), app(ExportManager::class));
            $this->fail('The export failure was treated as a successful job.');
        } catch (RuntimeException $exception) {
            $this->assertSame('storage unavailable', $exception->getMessage());
        }

        $this->assertSame(ReportExport::STATUS_FAILED, $export->fresh()->status);
    }

    #[Test]
    public function an_oversized_export_is_queued_before_the_report_result_is_built(): void
    {
        Queue::fake();
        $admin = $this->createOrganizationAdmin();
        $inner = app(ReportRegistry::class)->get('attendance-detail');
        $filters = ReportFilters::fromInput($admin, [], $inner->definition()->filters);
        $provider = new class($inner) implements ReportProvider
        {
            public bool $built = false;

            public function __construct(private ReportProvider $inner) {}

            public function definition(): ReportDefinition
            {
                return $this->inner->definition();
            }

            public function authorize(User $user): bool
            {
                return true;
            }

            public function build(ReportFilters $filters, int $page = 1): ReportResult
            {
                $this->built = true;

                throw new RuntimeException('built before queue');
            }

            public function exportRows(ReportFilters $filters): \Generator
            {
                yield from [];
            }

            public function estimateRows(ReportFilters $filters): int
            {
                return ExportManager::QUEUE_THRESHOLD + 1;
            }
        };

        $response = app(ExportManager::class)->download($provider, $filters, ReportFormat::Xlsx, $admin);

        $this->assertFalse($provider->built);
        $this->assertTrue($response->isRedirect());
        $this->assertDatabaseHas('report_exports', [
            'user_id' => $admin->getKey(),
            'status' => ReportExport::STATUS_QUEUED,
            'format' => 'xlsx',
        ]);
    }

    #[Test]
    public function purge_removes_expired_rows_missing_files_and_orphans_without_touching_a_live_artifact(): void
    {
        Storage::fake('local');
        $admin = $this->createOrganizationAdmin();
        $other = $this->createOrganizationAdmin();
        $live = 'report-exports/'.$other->organization_id.'/'.$other->getKey().'/live.csv';
        Storage::disk('local')->put($live, "Date\n");
        Storage::disk('local')->put('report-exports/9/9/orphan.csv', "Date\n");

        $liveExport = ReportExport::query()->create([
            'organization_id' => $other->organization_id,
            'user_id' => $other->getKey(),
            'report_key' => 'attendance-summary',
            'format' => 'csv',
            'filters' => [],
            'status' => ReportExport::STATUS_READY,
            'expires_at' => now()->addDay(),
        ]);
        $liveExport->forceFill(['disk_path' => $live])->save();

        $expired = ReportExport::query()->create([
            'organization_id' => $admin->organization_id,
            'user_id' => $admin->getKey(),
            'report_key' => 'attendance-summary',
            'format' => 'csv',
            'filters' => [],
            'status' => ReportExport::STATUS_READY,
            'expires_at' => now()->subHour(),
        ]);
        $expired->forceFill(['disk_path' => 'report-exports/'.$admin->organization_id.'/'.$admin->getKey().'/missing.csv'])->save();

        $this->artisan('reports:purge-exports')->assertSuccessful();

        $this->assertDatabaseMissing('report_exports', ['id' => $expired->getKey()]);
        $this->assertDatabaseHas('report_exports', ['id' => $liveExport->getKey()]);
        Storage::disk('local')->assertExists($live);
        Storage::disk('local')->assertMissing('report-exports/9/9/orphan.csv');
    }

    #[Test]
    public function leave_type_filters_change_digest_totals_and_the_cache_key(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-12 12:00:00', 'UTC'));
        $admin = $this->createOrganizationAdmin();
        $person = StaffMember::factory()->forOrganization($admin->organization)->create();
        $annual = $this->leaveType($admin->organization_id, 'Annual', 'ANNUAL');
        $sick = $this->leaveType($admin->organization_id, 'Sick leave', 'SICK');
        $this->balance($person, $annual, 10, 5);
        $this->balance($person, $sick, 10, 0);
        $this->leave($person, $annual, LeaveStatus::Submitted, '2026-12-01', '2026-12-02');
        $this->leave($person, $sick, LeaveStatus::Submitted, '2026-12-03', '2026-12-04');
        $this->leave($person, $annual, LeaveStatus::Approved, '2026-11-10', '2026-11-11');
        $this->leave($person, $sick, LeaveStatus::Approved, '2026-11-12', '2026-11-13');

        $scope = AnalyticsScope::forUser($admin, '2026-11-01', '2026-11-30');
        $digest = app(OperationalDigest::class);
        $all = $digest->leave($scope);
        $annualOnly = $digest->leave($scope, $annual->getKey());

        $this->assertSame(20, $all['entitled_days']);
        $this->assertSame(2, $all['pending']);
        $this->assertSame(10, $annualOnly['entitled_days']);
        $this->assertSame(5, $annualOnly['used_days']);
        $this->assertSame(1, $annualOnly['pending']);
        $this->assertSame(50, $annualOnly['utilisation']);

        config(['orbit.analytics_cache_seconds' => 60]);
        Cache::flush();
        $digest->leave($scope, $annual->getKey());
        $digest->leave($scope, $sick->getKey());
        $this->assertTrue(Cache::has($scope->cacheKey('leave.type.'.$annual->getKey())));
        $this->assertTrue(Cache::has($scope->cacheKey('leave.type.'.$sick->getKey())));

        $page = $this->actingAs($admin)
            ->get(route('org.reports.show', [
                'report' => 'leave-summary',
                'from' => '2026-11-01',
                'to' => '2026-11-30',
                'leave_type' => $annual->uuid,
            ]));
        $page->assertOk()->assertSee('5 of 10 entitled days used');
        $body = $this->tableBody($page->getContent());
        $this->assertStringContainsString('Annual', $body);
        $this->assertStringNotContainsString('Sick leave', $body);
    }

    #[Test]
    public function punctuality_pages_are_bounded_and_totals_stay_on_the_full_range(): void
    {
        $admin = $this->createOrganizationAdmin();
        $person = StaffMember::factory()->forOrganization($admin->organization)->create([
            'first_name' => 'Paged',
            'last_name' => 'Late',
        ]);
        $start = Carbon::parse('2026-01-01');
        for ($day = 0; $day < 51; $day++) {
            $this->record($person, $start->copy()->addDays($day)->toDateString(), AttendanceStatus::Late);
        }

        $first = $this->actingAs($admin)
            ->get(route('org.reports.show', [
                'report' => 'punctuality',
                'from' => '2026-01-01',
                'to' => '2026-02-20',
                'page' => 1,
            ]));
        $first->assertOk()->assertSee('51');
        $firstBody = $this->tableBody($first->getContent());
        $this->assertStringContainsString('2026-01-01', $firstBody);
        $this->assertStringNotContainsString('2026-02-20', $firstBody);

        $second = $this->actingAs($admin)
            ->get(route('org.reports.show', [
                'report' => 'punctuality',
                'from' => '2026-01-01',
                'to' => '2026-02-20',
                'page' => 2,
            ]));
        $second->assertOk()->assertSee('51');
        $secondBody = $this->tableBody($second->getContent());
        $this->assertStringContainsString('2026-02-20', $secondBody);
        $this->assertStringNotContainsString('2026-01-01', $secondBody);

        $this->actingAs($admin)
            ->get(route('org.reports.show', [
                'report' => 'punctuality',
                'from' => '2026-01-01',
                'to' => '2026-02-20',
                'page' => 3,
            ]))
            ->assertOk()
            ->assertSee('No late arrivals in this period.')
            ->assertSee('51');
    }

    #[Test]
    public function an_arrangement_filter_limits_schedule_coverage_totals(): void
    {
        $admin = $this->createOrganizationAdmin();
        $person = StaffMember::factory()->forOrganization($admin->organization)->create();
        app(LeavePlanner::class)->ensureArrangements($admin->organization);
        $remote = WorkArrangement::withoutTenantScope()
            ->where('organization_id', $admin->organization_id)
            ->where('code', 'remote')
            ->firstOrFail();
        $office = WorkArrangement::withoutTenantScope()
            ->where('organization_id', $admin->organization_id)
            ->where('code', 'office')
            ->firstOrFail();
        $this->assignment($admin->organization_id, $person, $remote, '2026-11-06');
        $this->assignment($admin->organization_id, $person, $office, '2026-11-07');

        $remotePage = $this->actingAs($admin)->get(route('org.reports.show', [
            'report' => 'schedule-coverage',
            'from' => '2026-11-01',
            'to' => '2026-11-30',
            'arrangement' => 'remote',
        ]));
        $remotePage->assertOk()->assertSee('Arrangement: Remote')->assertDontSee('Arrangement: Office');
        $this->assertMatchesRegularExpression('/Scheduled assignments<\/td>\s*<td[^>]*>\s*1\s*</', $remotePage->getContent());

        $officePage = $this->actingAs($admin)->get(route('org.reports.show', [
            'report' => 'schedule-coverage',
            'from' => '2026-11-01',
            'to' => '2026-11-30',
            'arrangement' => 'office',
        ]));
        $officePage->assertOk()->assertSee('Arrangement: Office')->assertDontSee('Arrangement: Remote');
        $this->assertMatchesRegularExpression('/Scheduled assignments<\/td>\s*<td[^>]*>\s*1\s*</', $officePage->getContent());

        $all = $this->actingAs($admin)->get(route('org.reports.show', [
            'report' => 'schedule-coverage',
            'from' => '2026-11-01',
            'to' => '2026-11-30',
        ]));
        $all->assertOk()->assertSee('Arrangement: Remote')->assertSee('Arrangement: Office');
        $this->assertMatchesRegularExpression('/Scheduled assignments<\/td>\s*<td[^>]*>\s*2\s*</', $all->getContent());

        $csv = $this->actingAs($admin)->get(route('org.reports.export', [
            'report' => 'schedule-coverage',
            'format' => 'csv',
            'from' => '2026-11-01',
            'to' => '2026-11-30',
            'arrangement' => 'remote',
        ]));
        $csv->assertOk();
        $this->assertMatchesRegularExpression('/Scheduled assignments"?,"?1/', $csv->streamedContent());
    }

    #[Test]
    public function a_person_who_changes_department_keeps_the_sum_of_each_status(): void
    {
        $admin = $this->createOrganizationAdmin();
        $first = Department::factory()->create(['organization_id' => $admin->organization_id, 'name' => 'Alpha']);
        $second = Department::factory()->create(['organization_id' => $admin->organization_id, 'name' => 'Beta']);
        $person = StaffMember::factory()->forOrganization($admin->organization)->create([
            'department_id' => $second->getKey(),
            'first_name' => 'Moved',
            'last_name' => 'Person',
        ]);
        $this->record($person, '2026-11-02', AttendanceStatus::Present, $first->getKey());
        $this->record($person, '2026-11-03', AttendanceStatus::Present, $second->getKey());

        $rows = app(WorkforceReport::class)->byPerson(
            $admin->organization,
            Carbon::parse('2026-11-01'),
            Carbon::parse('2026-11-30'),
            AnalyticsScope::forUser($admin, '2026-11-01', '2026-11-30'),
        );

        $this->assertCount(1, $rows);
        $this->assertSame(2, $rows[0]['present']);
        $this->assertSame(2, $rows[0]['total']);
        $this->assertStringContainsString('Alpha', $rows[0]['department']);
        $this->assertStringContainsString('Beta', $rows[0]['department']);
    }

    #[Test]
    public function attendance_detail_exports_eager_load_people_in_one_batch(): void
    {
        $admin = $this->createOrganizationAdmin();
        $department = Department::factory()->create(['organization_id' => $admin->organization_id, 'name' => 'Batch Dept']);
        $person = StaffMember::factory()->forOrganization($admin->organization)->create([
            'department_id' => $department->getKey(),
            'first_name' => 'Eager',
            'last_name' => 'Loader',
        ]);
        $this->record($person, '2026-11-02', AttendanceStatus::Present, $department->getKey());
        $this->record($person, '2026-11-03', AttendanceStatus::Present, $department->getKey());
        $this->record($person, '2026-11-04', AttendanceStatus::Late, $department->getKey());

        $provider = app(ReportRegistry::class)->get('attendance-detail');
        $filters = ReportFilters::fromInput($admin, [
            'from' => '2026-11-01',
            'to' => '2026-11-30',
        ], $provider->definition()->filters);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $rows = iterator_to_array($provider->exportRows($filters));
        $staffQueries = array_values(array_filter(
            DB::getQueryLog(),
            fn (array $query): bool => str_contains($query['query'], 'staff_members'),
        ));
        DB::disableQueryLog();

        $this->assertCount(3, $rows);
        $this->assertSame('Eager Loader', $rows[0]['name']);
        $this->assertCount(1, $staffQueries);
    }

    /**
     * @param  array<string, mixed>  $scope
     */
    private function readyExport(User $user, array $scope): ReportExport
    {
        $path = 'report-exports/'.$user->organization_id.'/'.$user->getKey().'/'.uniqid('artifact-', true).'.csv';
        Storage::disk('local')->put($path, "Date\n");

        $export = ReportExport::query()->create([
            'organization_id' => $user->organization_id,
            'user_id' => $user->getKey(),
            'report_key' => 'attendance-summary',
            'format' => 'csv',
            'filters' => [],
            'generation_scope' => $scope,
            'status' => ReportExport::STATUS_READY,
            'expires_at' => now()->addDay(),
        ]);
        $export->forceFill(['disk_path' => $path])->save();

        return $export;
    }

    private function tableBody(string $html): string
    {
        if (preg_match('/<tbody>(.*)<\/tbody>/s', $html, $matches) !== 1) {
            return '';
        }

        return $matches[1];
    }

    private function leaveType(int $organizationId, string $name, string $code): LeaveType
    {
        return LeaveType::query()->create([
            'organization_id' => $organizationId,
            'name' => $name,
            'code' => $code,
            'paid' => true,
            'requires_balance' => true,
            'is_active' => true,
        ]);
    }

    private function balance(StaffMember $person, LeaveType $type, int $entitled, int $used): void
    {
        LeaveBalance::query()->create([
            'organization_id' => $person->organization_id,
            'staff_member_id' => $person->getKey(),
            'leave_type_id' => $type->getKey(),
            'year' => 2026,
            'period_start' => '2026-01-01',
            'period_end' => '2026-12-31',
            'entitled_days' => $entitled,
            'used_days' => $used,
            'pending_days' => 0,
            'adjusted_days' => 0,
        ]);
    }

    private function leave(StaffMember $person, LeaveType $type, LeaveStatus $status, string $start, string $end): void
    {
        LeaveRequest::query()->create([
            'organization_id' => $person->organization_id,
            'staff_member_id' => $person->getKey(),
            'leave_type_id' => $type->getKey(),
            'status' => $status->value,
            'starts_on' => $start,
            'ends_on' => $end,
            'day_count' => 2,
            'reason' => 'Family event',
        ]);
    }

    private function assignment(int $organizationId, StaffMember $person, WorkArrangement $arrangement, string $date): void
    {
        $shift = Shift::query()->create([
            'organization_id' => $organizationId,
            'work_arrangement_id' => $arrangement->getKey(),
            'name' => $arrangement->name.' '.$date,
            'starts_at' => '08:00:00',
            'ends_at' => '16:00:00',
            'effective_from' => '2026-11-01',
            'status' => ShiftStatus::Published,
        ]);
        ShiftAssignment::query()->create([
            'organization_id' => $organizationId,
            'shift_id' => $shift->getKey(),
            'staff_member_id' => $person->getKey(),
            'work_arrangement_id' => $arrangement->getKey(),
            'work_date' => $date,
        ]);
    }

    private function record(StaffMember $person, string $date, AttendanceStatus $status, ?int $departmentId = null): void
    {
        AttendanceRecord::query()->create([
            'organization_id' => $person->organization_id,
            'staff_member_id' => $person->getKey(),
            'department_id' => $departmentId ?? $person->department_id,
            'work_date' => $date,
            'period_key' => AttendanceRecord::dayPeriod($date),
            'status' => $status->value,
            'late_minutes' => $status === AttendanceStatus::Late ? 10 : 0,
        ]);
    }
}
