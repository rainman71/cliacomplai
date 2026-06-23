<?php

namespace Database\Seeders;

use App\Models\LabUser;
use App\Models\User;
use App\Services\LabProvisioner;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $lab = app(LabProvisioner::class)->create(['name' => 'Triad Behavioral Resources']);

        // name, email, roles[], super-admin
        $staff = [
            ['Dr. Lab Director', 'lab.director@example.com', ['lab_director'], false],
            ['Tech Supervisor', 'tech.supervisor@example.com', ['tech_supervisor'], false],
            ['General Supervisor', 'general.supervisor@example.com', ['general_supervisor'], false],
            ['Compliance Specialist', 'compliance@example.com', ['compliance_specialist'], false],
            ['Safety Officer', 'safety.officer@example.com', ['safety_officer'], false],
            ['Technical Staff', 'tech.staff@example.com', ['tech_staff'], false],
        ];

        foreach ($staff as [$name, $email, $roles, $super]) {
            $user = User::updateOrCreate(
                ['email' => $email],
                ['name' => $name, 'active' => true, 'is_super_admin' => $super, 'password' => Hash::make('password')],
            );
            $membership = LabUser::updateOrCreate(['lab_id' => $lab->id, 'user_id' => $user->id], ['active' => true]);
            $membership->syncRoles($roles);
        }
    }
}
