<?php

namespace Tests\Feature;

use App\Models\Lab;
use App\Models\LabUser;
use App\Models\Obligation;
use App\Models\User;
use App\Services\RecipientResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the Lab Director Designee role: it edits/signs like the director but cannot manage users,
 * and it is reached everywhere the "Lab Director / designee" human role is resolved to recipients.
 */
class DesigneeRoleTest extends TestCase
{
    use RefreshDatabase;

    private Lab $lab;

    protected function setUp(): void
    {
        parent::setUp();
        $this->lab = $this->makeLab(); // also sets the active lab (RecipientResolver is lab-scoped)
    }

    private function memberWithRoles(array $roles): User
    {
        $user = User::factory()->create();
        LabUser::create(['lab_id' => $this->lab->id, 'user_id' => $user->id, 'active' => true])
            ->syncRoles($roles);

        return $user;
    }

    public function test_designee_can_edit_and_sign_but_not_manage_users(): void
    {
        $designee = $this->memberWithRoles(['lab_director_designee']);

        $this->assertTrue($designee->canEditLab($this->lab), 'designee should be an editor');
        $this->assertFalse($designee->canManageLab($this->lab), 'designee must NOT be a manager');
    }

    public function test_director_slash_designee_role_resolves_to_both(): void
    {
        $director = $this->memberWithRoles(['lab_director']);
        $designee = $this->memberWithRoles(['lab_director_designee']);
        $specialist = $this->memberWithRoles(['compliance_specialist']);

        $ids = app(RecipientResolver::class)->forRole('Lab Director / designee')->pluck('id');

        $this->assertTrue($ids->contains($director->id));
        $this->assertTrue($ids->contains($designee->id));
        $this->assertFalse($ids->contains($specialist->id));
    }

    public function test_plain_director_and_plain_designee_roles_stay_distinct(): void
    {
        $director = $this->memberWithRoles(['lab_director']);
        $designee = $this->memberWithRoles(['lab_director_designee']);

        $directors = app(RecipientResolver::class)->forRole('Lab Director')->pluck('id');
        $this->assertTrue($directors->contains($director->id));
        $this->assertFalse($directors->contains($designee->id), 'plain "Lab Director" must not pull in designees');

        $designees = app(RecipientResolver::class)->forRole('Designee')->pluck('id');
        $this->assertTrue($designees->contains($designee->id));
        $this->assertFalse($designees->contains($director->id));
    }

    public function test_c09_signer_reaches_both_director_and_designee(): void
    {
        // C09 "Patient result approval" ships with the required signer "Lab Director / designee".
        $director = $this->memberWithRoles(['lab_director']);
        $designee = $this->memberWithRoles(['lab_director_designee']);
        $c09 = Obligation::where('code', 'C09')->firstOrFail();
        $signerRole = $c09->requiredSigners()->firstOrFail()->signer_role;

        $ids = app(RecipientResolver::class)->forRole($signerRole)->pluck('id');

        $this->assertSame('Lab Director / designee', $signerRole);
        $this->assertTrue($ids->contains($director->id));
        $this->assertTrue($ids->contains($designee->id));
    }
}
