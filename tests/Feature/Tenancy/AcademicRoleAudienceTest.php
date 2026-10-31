<?php

namespace Tests\Feature\Tenancy;

use App\Enums\OrganizationType;
use App\Models\Organization;
use App\Tenancy\RoleAudience;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AcademicRoleAudienceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function student_is_not_offered_to_a_public_institution_like_epa(): void
    {
        $epa = Organization::factory()->create(['type' => OrganizationType::PublicInstitution, 'name' => 'EPA Demo']);
        $admin = $this->createOrganizationAdmin($epa);

        $this->assertFalse(RoleAudience::allows('student', $epa->type));
        $this->assertArrayNotHasKey('student', RoleAudience::invitableOptions($epa));

        $this->actingAs($admin)
            ->get(route('org.users.index'))
            ->assertOk()
            ->assertDontSee('>Student<', false);

        $this->actingAs($admin)
            ->post(route('org.invitations.store'), ['email' => 'learner@example.test', 'role' => 'student'])
            ->assertSessionHasErrors('role');
    }

    #[Test]
    public function student_is_offered_to_an_educational_institution(): void
    {
        $upsa = Organization::factory()->create(['type' => OrganizationType::EducationalInstitution, 'name' => 'UPSA Demo']);
        $admin = $this->createOrganizationAdmin($upsa);

        $this->assertTrue(RoleAudience::allows('student', $upsa->type));
        $this->assertArrayHasKey('student', RoleAudience::invitableOptions($upsa));

        $this->actingAs($admin)
            ->get(route('org.users.index'))
            ->assertOk()
            ->assertSee('Student');
    }
}
