<?php

namespace App\Providers;

use App\Models\Lab;
use App\Models\User;
use App\Services\Drive\DriveClient;
use App\Services\Drive\DriveFiler;
use App\Services\Drive\DriveNaming;
use App\Services\Drive\DriveScanner;
use App\Services\Drive\GoogleDriveClient;
use App\Services\Drive\GoogleDriveFiler;
use App\Services\Drive\GoogleDriveScanner;
use App\Services\Drive\NullDriveFiler;
use App\Services\Drive\NullDriveScanner;
use App\Services\Signature\ManualGoogleESignature;
use App\Services\Signature\SignatureProvider;
use App\Support\CurrentLab;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Active-lab context for the request/process (read by the BelongsToLab global scope).
        $this->app->singleton(CurrentLab::class);

        // Swap this one binding to upgrade to DocuSign later — nothing else changes.
        $this->app->bind(SignatureProvider::class, ManualGoogleESignature::class);

        // Real Drive filing once a service-account key is configured; Null filer otherwise.
        // The root folder is taken from the ACTIVE lab (falling back to the global config).
        $this->app->bind(DriveFiler::class, function ($app) {
            $credentials = config('services.google.drive_credentials');

            if ($credentials && is_file($credentials)) {
                $root = $app->make(CurrentLab::class)->get()?->drive_root_folder_id
                    ?: config('services.google.drive_root_folder_id');

                return new GoogleDriveFiler(
                    $app->make(DriveNaming::class),
                    new GoogleDriveClient($credentials),
                    $root,
                );
            }

            return new NullDriveFiler($app->make(DriveNaming::class));
        });

        // Raw Drive client — bound only when a key is configured. Used by the lab-onboarding wizard
        // to probe a pasted Shared Drive id before saving it. Left unbound (not Null) when unconfigured
        // so callers can detect "Drive isn't set up on this server" rather than silently no-op.
        $this->app->bind(DriveClient::class, function ($app) {
            $credentials = config('services.google.drive_credentials');

            if ($credentials && is_file($credentials)) {
                return new GoogleDriveClient($credentials);
            }

            throw new \RuntimeException('Google Drive is not configured (GOOGLE_DRIVE_CREDENTIALS).');
        });

        // Drive evidence scanner (auto-ingestion's "eyes") — Google when a key is configured, else Null.
        $this->app->bind(DriveScanner::class, function ($app) {
            $credentials = config('services.google.drive_credentials');

            return ($credentials && is_file($credentials))
                ? new GoogleDriveScanner(new GoogleDriveClient($credentials))
                : new NullDriveScanner();
        });
    }

    public function boot(): void
    {
        // Lab-scoped management permission (super admin passes via canManageLab()).
        Gate::define('manage-lab-users', fn (User $user, Lab $lab) => $user->canManageLab($lab));

        // Cross-lab administration is super-admin only.
        Gate::define('manage-labs', fn (User $user) => $user->isSuperAdmin());
    }
}
