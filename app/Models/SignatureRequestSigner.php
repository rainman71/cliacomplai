<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SignatureRequestSigner extends Model
{
    protected $fillable = [
        'signature_request_id', 'signer_name', 'signer_email', 'status', 'signed_date',
    ];

    protected function casts(): array
    {
        return ['signed_date' => 'datetime'];
    }

    public function signatureRequest(): BelongsTo
    {
        return $this->belongsTo(SignatureRequest::class);
    }
}
