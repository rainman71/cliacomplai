<?php

namespace App\Console\Commands;

use App\Services\LabProvisioner;
use Illuminate\Console\Command;

class CreateLab extends Command
{
    protected $signature = 'lab:create {name : The lab name} {--clia= : CLIA number} {--timezone=America/New_York} {--drive= : Drive root folder id}';

    protected $description = 'Create a lab and clone the 13-obligation CLIA register into it.';

    public function handle(LabProvisioner $provisioner): int
    {
        $lab = $provisioner->create([
            'name' => $this->argument('name'),
            'clia_number' => $this->option('clia'),
            'timezone' => $this->option('timezone'),
            'drive_root_folder_id' => $this->option('drive'),
        ]);

        $this->info("Created lab #{$lab->id} \"{$lab->name}\" with {$lab->obligations()->count()} obligations.");
        $this->line('Add members and roles in the app under Users & Access, or via a super admin.');

        return self::SUCCESS;
    }
}
