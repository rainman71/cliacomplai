<?php

namespace Tests\Feature;

use App\Models\LabUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InviteUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_invite_a_new_user_by_email(): void
    {
        $lab = $this->makeLab();
        $this->actingInLab($lab, 'lab_director');

        Livewire::test('user-management', ['lab' => $lab])
            ->set('inviteEmail', 'newhire@lab.com')
            ->set('inviteName', 'New Hire')
            ->call('inviteByEmail')
            ->assertHasNoErrors();

        $user = User::where('email', 'newhire@lab.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('New Hire', $user->name);
        $this->assertTrue($user->active);
        $this->assertNull($user->google_sub); // hasn't signed in yet → shows as "invited"

        $membership = LabUser::where('lab_id', $lab->id)->where('user_id', $user->id)->first();
        $this->assertNotNull($membership);
        $this->assertContains('tech_staff', $membership->roleNames()); // default role
    }

    public function test_inviting_an_existing_email_adds_them_without_duplicating(): void
    {
        $lab = $this->makeLab();
        $this->actingInLab($lab, 'lab_director');
        $existing = User::factory()->create(['email' => 'exists@lab.com', 'google_sub' => 'g-1']);

        Livewire::test('user-management', ['lab' => $lab])
            ->set('inviteEmail', 'exists@lab.com')
            ->call('inviteByEmail')
            ->assertHasNoErrors();

        $this->assertSame(1, User::where('email', 'exists@lab.com')->count()); // no duplicate user
        $this->assertTrue(LabUser::where('lab_id', $lab->id)->where('user_id', $existing->id)->exists());
    }

    public function test_invalid_email_invites_nobody(): void
    {
        $lab = $this->makeLab();
        $this->actingInLab($lab, 'lab_director');
        $before = User::count();

        Livewire::test('user-management', ['lab' => $lab])
            ->set('inviteEmail', 'not-an-email')
            ->call('inviteByEmail');

        $this->assertSame($before, User::count()); // nothing created
    }

    public function test_read_only_user_cannot_reach_user_management(): void
    {
        $lab = $this->makeLab();
        $this->actingInLab($lab, 'tech_staff'); // member but not a manager

        // The Users & Access page (where inviting lives) is manager-gated at the route.
        $this->get(route('users', $lab->id))->assertForbidden();
    }
}
