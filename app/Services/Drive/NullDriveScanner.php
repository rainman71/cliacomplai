<?php

namespace App\Services\Drive;

use App\Models\Lab;

/** Default scanner when no Drive credentials are configured — sees nothing. */
class NullDriveScanner implements DriveScanner
{
    public function isConfigured(): bool
    {
        return false;
    }

    public function scan(Lab $lab): array
    {
        return [];
    }
}
