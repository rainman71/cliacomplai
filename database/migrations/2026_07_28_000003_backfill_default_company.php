<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Give existing labs a home company. No-op on a fresh/empty database (seeders and tests create
 * their own companies/labs). On a populated database, creates a single default "Rightsize"
 * company and assigns every currently-unassigned lab to it — real management companies are then
 * added and labs reassigned from the Management Companies UI.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('labs')->count() === 0) {
            return; // fresh DB — nothing to backfill
        }

        $companyId = DB::table('management_companies')->insertGetId([
            'name' => 'Rightsize',
            'slug' => 'rightsize',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('labs')->whereNull('management_company_id')->update(['management_company_id' => $companyId]);
    }

    public function down(): void
    {
        DB::table('labs')->update(['management_company_id' => null]);
        DB::table('management_companies')->where('slug', 'rightsize')->delete();
    }
};
