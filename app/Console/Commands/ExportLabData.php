<?php

namespace App\Console\Commands;

use App\Models\Lab;
use App\Models\Obligation;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;

/**
 * Export ONE lab's real compliance data — its profile/CLIA/Drive-root plus each obligation's
 * dates/links/status keyed by obligation CODE — to a small JSON file. Pairs with
 * compliance:import-lab-data, which merges it onto a lab in another environment by code (so it
 * never disturbs obligations the target has that the source doesn't, e.g. a newer C17). Carries no
 * users, no other labs, no demo/test rows.
 */
class ExportLabData extends Command
{
    protected $signature = 'compliance:export-lab-data {lab : Lab id or exact name} {path : Output JSON file}';

    protected $description = "Export one lab's compliance data (profile + obligation dates/links by code)";

    /** Obligation columns that hold real, environment-portable data (not the template definition). */
    public const OBLIGATION_FIELDS = ['last_completed', 'next_due', 'document_link', 'drive_file_id', 'signature_status', 'active'];

    public function handle(): int
    {
        $lab = $this->resolveLab($this->argument('lab'));
        if (! $lab) {
            $this->error('Lab not found: '.$this->argument('lab'));

            return self::FAILURE;
        }

        $obligations = [];
        foreach (Obligation::withoutGlobalScopes()->where('lab_id', $lab->id)->orderBy('code')->get() as $o) {
            $row = [];
            foreach (self::OBLIGATION_FIELDS as $f) {
                $v = $o->getAttribute($f);
                $row[$f] = $v instanceof CarbonInterface ? $v->toDateString() : $v;
            }
            $obligations[$o->code] = $row;
        }

        $payload = [
            'lab' => [
                'name' => $lab->name,
                'clia_number' => $lab->clia_number,
                'timezone' => $lab->timezone,
                'drive_root_folder_id' => $lab->drive_root_folder_id,
                'profile' => $lab->profile,
            ],
            'obligations' => $obligations,
        ];

        file_put_contents($this->argument('path'), json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $this->info(sprintf('Exported lab "%s" — %d obligations -> %s', $lab->name, count($obligations), $this->argument('path')));

        return self::SUCCESS;
    }

    private function resolveLab(string $idOrName): ?Lab
    {
        $q = Lab::withoutGlobalScopes();

        return is_numeric($idOrName) ? $q->find((int) $idOrName) : $q->where('name', $idOrName)->first();
    }
}
