<?php

namespace Tests;

use App\Models\Lab;
use App\Models\LabUser;
use App\Models\User;
use App\Services\LabProvisioner;
use App\Support\CurrentLab;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** Create a lab with the 17-obligation template and make it the active lab. */
    protected function makeLab(string $name = 'Test Lab'): Lab
    {
        $lab = app(LabProvisioner::class)->create(['name' => $name]);
        app(CurrentLab::class)->set($lab);

        return $lab;
    }

    /** Create a user with the given role(s) at a lab (or a super admin) and authenticate them. */
    protected function actingInLab(Lab $lab, array|string $roles = ['compliance_specialist'], bool $superAdmin = false): User
    {
        $user = User::factory()->create(['is_super_admin' => $superAdmin]);

        if (! $superAdmin) {
            $membership = LabUser::create(['lab_id' => $lab->id, 'user_id' => $user->id, 'active' => true]);
            $membership->syncRoles((array) $roles);
        }

        $this->actingAs($user);

        return $user;
    }
}
