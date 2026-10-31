<?php

namespace Tests\Feature\Tenancy;

use App\Actions\Tenancy\MakeDomainPrimary;
use App\Enums\BrandThemeMode;
use App\Models\Organization;
use App\Models\OrganizationDomain;
use App\Tenancy\BrandingService;
use App\Tenancy\Hostname;
use App\Tenancy\WorkspaceBranding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BrandingLifecycleTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_draft_logo_does_not_replace_the_published_file(): void
    {
        Storage::fake('organizations');
        $admin = $this->createOrganizationAdmin();
        $organization = $admin->organization;

        $this->actingAs($admin)
            ->put(route('org.organizations.branding.update', $organization), [
                'logo' => UploadedFile::fake()->image('live.png', 64, 64),
            ])
            ->assertRedirect();
        $this->actingAs($admin)->post(route('org.organizations.branding.publish', $organization));

        $published = $organization->fresh()->branding->logo_path;
        $this->assertNotNull($published);
        Storage::disk('organizations')->assertExists($published);

        $this->actingAs($admin)
            ->put(route('org.organizations.branding.update', $organization), [
                'logo' => UploadedFile::fake()->image('draft.webp', 64, 64),
            ])
            ->assertRedirect();

        $record = $organization->fresh()->branding;
        $draftPath = $record->draft['logo_path'] ?? null;

        $this->assertNotNull($draftPath);
        $this->assertStringContainsString('/branding/draft/', $draftPath);
        $this->assertSame($published, $record->logo_path);
        Storage::disk('organizations')->assertExists($published);
        Storage::disk('organizations')->assertExists($draftPath);
    }

    #[Test]
    public function publishing_promotes_the_draft_and_deletes_unused_files(): void
    {
        Storage::fake('organizations');
        $admin = $this->createOrganizationAdmin();
        $organization = $admin->organization;

        $this->actingAs($admin)
            ->put(route('org.organizations.branding.update', $organization), [
                'logo' => UploadedFile::fake()->image('old.png', 64, 64),
            ]);
        $this->actingAs($admin)->post(route('org.organizations.branding.publish', $organization));

        $oldPath = $organization->fresh()->branding->logo_path;

        $this->actingAs($admin)
            ->put(route('org.organizations.branding.update', $organization), [
                'logo' => UploadedFile::fake()->image('new.webp', 64, 64),
            ]);

        $draftPath = $organization->fresh()->branding->draft['logo_path'];

        $this->actingAs($admin)->post(route('org.organizations.branding.publish', $organization));

        $record = $organization->fresh()->branding;
        $this->assertNull($record->draft);
        $this->assertNotSame($oldPath, $record->logo_path);
        $this->assertStringEndsWith('logo.webp', $record->logo_path);
        Storage::disk('organizations')->assertExists($record->logo_path);
        Storage::disk('organizations')->assertMissing($oldPath);
        Storage::disk('organizations')->assertMissing($draftPath);
        $this->assertFalse(Storage::disk('organizations')->directoryExists('organizations/'.$organization->uuid.'/branding/draft'));
    }

    #[Test]
    public function preview_applies_cleared_draft_fields_and_draft_asset_urls(): void
    {
        Storage::fake('organizations');
        $admin = $this->createOrganizationAdmin();
        $organization = $admin->organization;
        $organization->branding->update([
            'display_name' => 'Published Name',
            'login_heading' => 'Published heading',
            'primary_color' => '#111111',
            'published_at' => now(),
            'draft' => [
                'display_name' => null,
                'login_heading' => 'Draft heading',
                'primary_color' => '#AA3300',
                'logo_path' => 'organizations/'.$organization->uuid.'/branding/draft/logo-preview.png',
            ],
        ]);
        Storage::disk('organizations')->put(
            'organizations/'.$organization->uuid.'/branding/draft/logo-preview.png',
            'png',
        );

        $preview = WorkspaceBranding::preview($organization, $organization->fresh()->branding);

        $this->assertSame($organization->name, $preview->displayName);
        $this->assertSame('Draft heading', $preview->loginHeading);
        $this->assertSame('#AA3300', $preview->primary);
        $this->assertStringContainsString('/branding/preview/'.$organization->uuid.'/logo', (string) $preview->logoUrl);

        $this->actingAs($admin)
            ->get(route('org.organizations.branding.preview', $organization))
            ->assertOk()
            ->assertSee('Draft heading')
            ->assertSee('#AA3300', false)
            ->assertSee('/branding/preview/'.$organization->uuid.'/logo', false);

        $this->actingAs($admin)
            ->get(route('branding.preview-asset', ['organization' => $organization, 'slot' => 'logo']))
            ->assertOk();

        $this->actingAs($admin)->get(route('branding.asset', ['slot' => 'logo']))->assertNotFound();
    }

    #[Test]
    public function changing_the_primary_hostname_invalidates_branding_asset_urls(): void
    {
        Storage::fake('organizations');
        $admin = $this->createOrganizationAdmin();
        $organization = $admin->organization;
        $path = 'organizations/'.$organization->uuid.'/branding/logo.png';
        $organization->branding->update([
            'logo_path' => $path,
            'published_at' => now(),
        ]);
        Storage::disk('organizations')->put($path, 'png');

        $service = app(BrandingService::class);
        $service->forget($organization);
        $before = $service->for($organization);
        $this->assertStringContainsString($organization->primaryHost(), (string) $before->logoUrl);

        $alias = OrganizationDomain::factory()->for($organization)->alias('altbrand')->create();
        app(MakeDomainPrimary::class)->handle($alias, $admin);

        $after = $service->for($organization->fresh());
        $this->assertStringContainsString(Hostname::platformHostFor('altbrand'), (string) $after->logoUrl);
    }

    #[Test]
    public function a_locked_theme_hides_the_toggle_on_the_login_page(): void
    {
        $organization = Organization::factory()->create();
        $organization->branding->update([
            'theme_mode' => BrandThemeMode::Light,
            'published_at' => now(),
        ]);
        app(BrandingService::class)->forget($organization);

        $this->fromTenant($organization)
            ->get('/login')
            ->assertOk()
            ->assertDontSee('themeToggle', false)
            ->assertSee('dataset.themeLock', false);
    }

    #[Test]
    public function tenant_login_footer_links_use_the_central_origin(): void
    {
        $organization = Organization::factory()->create();

        $this->fromTenant($organization)
            ->get('/login')
            ->assertOk()
            ->assertSee('http://orbit.test/terms', false)
            ->assertSee('http://orbit.test/privacy', false)
            ->assertSee('http://orbit.test/contact', false)
            ->assertDontSee('http://'.$organization->primaryHost().'/terms', false);
    }
}
