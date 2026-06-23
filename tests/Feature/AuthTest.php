<?php

namespace Tests\Feature;

use App\Models\Obligation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_dev_login_works_in_local_env(): void
    {
        $this->app['env'] = 'local';
        $user = User::factory()->create(['is_super_admin' => true]);

        $this->get(route('dev.login'))->assertRedirect(route('portfolio'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_dev_login_is_blocked_outside_local(): void
    {
        $this->get(route('dev.login'))->assertNotFound();
        $this->assertGuest();
    }

    public function test_new_google_user_is_created_active_with_no_lab_access(): void
    {
        config(['services.google.allowed_domain' => 'rightsizelabs.com']);
        $this->mockSocialite('google-123', 'Dr. Director', 'director@rightsizelabs.com');

        // Logs in, but lands on an empty portfolio until an admin grants a lab.
        $this->get(route('google.callback'))->assertRedirect(route('portfolio'));

        $this->assertDatabaseHas('users', [
            'email' => 'director@rightsizelabs.com', 'active' => true,
        ]);
        $this->assertAuthenticated();
    }

    public function test_disabled_account_cannot_log_in(): void
    {
        config(['services.google.allowed_domain' => 'rightsizelabs.com']);
        User::factory()->create(['email' => 'x@rightsizelabs.com', 'google_sub' => 'g-x', 'active' => false]);
        $this->mockSocialite('g-x', 'X', 'x@rightsizelabs.com');

        $this->get(route('google.callback'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_google_callback_rejects_outside_domain(): void
    {
        config(['services.google.allowed_domain' => 'rightsizelabs.com']);
        $this->mockSocialite('g-999', 'Outsider', 'someone@gmail.com');

        $this->get(route('google.callback'))->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'someone@gmail.com']);
    }

    public function test_edits_are_attributed_to_the_logged_in_user(): void
    {
        $lab = $this->makeLab();
        $user = $this->actingInLab($lab, 'compliance_specialist');
        $c03 = Obligation::where('code', 'C03')->first();

        Livewire::test('compliance-dashboard', ['lab' => $lab])
            ->set("form.{$c03->id}.notes", 'Confirmed cadence with NC');

        $this->assertDatabaseHas('audit_log', [
            'entity_id' => $c03->id, 'field' => 'notes', 'changed_by' => $user->id,
        ]);
    }

    private function mockSocialite(string $id, string $name, string $email): void
    {
        $abstractUser = Mockery::mock(SocialiteUser::class);
        $abstractUser->shouldReceive('getId')->andReturn($id);
        $abstractUser->shouldReceive('getName')->andReturn($name);
        $abstractUser->shouldReceive('getEmail')->andReturn($email);

        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->andReturn($abstractUser);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }
}
