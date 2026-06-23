<?php

namespace App\Services\Signature;

use App\Models\SignatureRequest;
use Carbon\CarbonImmutable;

/**
 * Default provider. Google Workspace eSignature has no public API, so the actual
 * signing happens in Docs/Drive and Google files the completed PDF to Drive itself.
 * This provider just tracks state: it seeds the signer rows from the obligation's
 * required signers and records when the request went out. The owner marks it
 * "Complete" in the app (no webhook).
 */
class ManualGoogleESignature implements SignatureProvider
{
    public function key(): string
    {
        return 'google_esignature';
    }

    public function isAutomated(): bool
    {
        return false;
    }

    public function send(SignatureRequest $request): void
    {
        $request->forceFill([
            'provider' => $this->key(),
            'sent_date' => CarbonImmutable::now()->toDateString(),
            'status' => 'out_for_signature',
        ])->save();

        // Seed one signer row per required signer if not already present.
        if ($request->signers()->count() === 0) {
            foreach ($request->obligation->requiredSigners as $required) {
                $request->signers()->create([
                    'signer_name' => $required->signer_role,
                    'signer_email' => $required->user?->email,
                    'status' => 'pending',
                ]);
            }
        }
    }

    public function refreshStatus(SignatureRequest $request): void
    {
        // No API to poll — status is advanced by the owner in the UI.
    }
}
