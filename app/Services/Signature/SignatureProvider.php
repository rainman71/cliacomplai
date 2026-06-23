<?php

namespace App\Services\Signature;

use App\Models\SignatureRequest;

/**
 * Abstraction over e-signature backends so the provider can be swapped without
 * touching the rest of the app (plan §0, §5).
 *
 *  - ManualGoogleESignature (current): signing happens in Google Docs/Drive; the app
 *    tracks status and the owner advances it. isAutomated() === false.
 *  - DocuSignProvider (future upgrade): sends envelopes and a webhook flips state
 *    hands-free. isAutomated() === true.
 */
interface SignatureProvider
{
    /** Stable identifier stored on signature_requests.provider. */
    public function key(): string;

    /** Whether completion is detected automatically (webhook/API) vs. marked by a human. */
    public function isAutomated(): bool;

    /**
     * Begin a signature run: record sent_date, seed per-signer rows, and (for automated
     * providers) dispatch the request to the external service.
     */
    public function send(SignatureRequest $request): void;

    /**
     * Pull the latest signer statuses from the backend. No-op for manual providers,
     * where the owner updates status in the app instead.
     */
    public function refreshStatus(SignatureRequest $request): void;
}
