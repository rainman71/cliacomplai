<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migrate the existing single-tenant data into "Lab #1". No-op on a fresh/empty database
 * (where seeders create labs + memberships directly). Runs BEFORE users.role is dropped.
 */
return new class extends Migration
{
    private array $tenantTables = [
        'obligations', 'completions', 'signature_requests',
        'credentials', 'reminders_sent', 'audit_log',
    ];

    public function up(): void
    {
        // Only backfill if there is existing single-tenant data to migrate.
        if (DB::table('users')->count() === 0 && DB::table('obligations')->count() === 0) {
            return;
        }

        DB::transaction(function () {
            $labId = DB::table('labs')->insertGetId([
                'name' => 'Triad Behavioral Resources',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($this->tenantTables as $table) {
                DB::table($table)->whereNull('lab_id')->update(['lab_id' => $labId]);
            }

            $hasRole = Schema::hasColumn('users', 'role');

            foreach (DB::table('users')->get() as $user) {
                $membershipId = DB::table('lab_user')->insertGetId([
                    'lab_id' => $labId,
                    'user_id' => $user->id,
                    'active' => $user->active ?? true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $role = $hasRole ? ($user->role ?? null) : null;
                if ($role) {
                    DB::table('lab_user_role')->insert([
                        'lab_user_id' => $membershipId,
                        'role' => $role,
                    ]);
                }
            }

            // Rightsize HQ super admin.
            DB::table('users')->where('email', 'ryordy@greenlightholdings.biz')
                ->update(['is_super_admin' => true]);
        });
    }

    public function down(): void
    {
        DB::table('lab_user_role')->delete();
        DB::table('lab_user')->delete();
        DB::table('labs')->delete();
        foreach ($this->tenantTables as $table) {
            DB::table($table)->update(['lab_id' => null]);
        }
    }
};
