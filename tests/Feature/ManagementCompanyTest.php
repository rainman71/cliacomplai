<?php

namespace Tests\Feature;

use App\Models\Lab;
use App\Models\ManagementCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 1 of the company tier: the ManagementCompany model, the labs.management_company_id link,
 * and the populated-database backfill. No current behavior changes — companies are additive.
 */
class ManagementCompanyTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_company_owns_labs_and_a_lab_knows_its_company(): void
    {
        $company = ManagementCompany::create(['name' => 'Acme Lab Group', 'slug' => 'acme']);
        $lab = $this->makeLab('Acme Tox');
        $lab->update(['management_company_id' => $company->id]);

        $this->assertTrue($company->labs->contains($lab));
        $this->assertSame($company->id, $lab->fresh()->company->id);
        $this->assertSame('Acme Lab Group', $lab->fresh()->company->name);
    }

    public function test_company_defaults_to_active_and_is_nullable_on_a_lab(): void
    {
        $company = ManagementCompany::create(['name' => 'Beta Group']);
        $this->assertTrue($company->fresh()->active); // DB default applies on reload

        // A lab may exist before a company is chosen — the link is nullable.
        $lab = $this->makeLab('Unassigned Lab');
        $this->assertNull($lab->management_company_id);
        $this->assertNull($lab->company);
    }

    public function test_deleting_a_company_nulls_the_link_not_the_lab(): void
    {
        $company = ManagementCompany::create(['name' => 'Gamma Group']);
        $lab = $this->makeLab('Gamma Lab');
        $lab->update(['management_company_id' => $company->id]);

        $company->delete();

        $this->assertNotNull($lab->fresh(), 'the lab must survive its company being deleted');
        $this->assertNull($lab->fresh()->management_company_id);
    }

    public function test_backfill_assigns_existing_unassigned_labs_to_a_default_company(): void
    {
        // Simulate a populated production database: a lab with no company yet.
        $lab = $this->makeLab('Legacy Lab');
        DB::table('labs')->where('id', $lab->id)->update(['management_company_id' => null]);

        // Re-run the backfill migration's logic on the now-populated database.
        (require database_path('migrations/2026_07_28_000003_backfill_default_company.php'))->up();

        $company = ManagementCompany::where('slug', 'rightsize')->first();
        $this->assertNotNull($company, 'backfill should create the default company');
        $this->assertSame($company->id, $lab->fresh()->management_company_id);
    }
}
