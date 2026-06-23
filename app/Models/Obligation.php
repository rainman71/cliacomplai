<?php

namespace App\Models;

use App\Enums\ComplianceStatus;
use App\Models\Concerns\BelongsToLab;
use App\Services\ComplianceStatusService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Obligation extends Model
{
    use BelongsToLab;

    protected $fillable = [
        'lab_id', 'code', 'category', 'name', 'frequency_label', 'interval_months',
        'owner_role', 'last_completed', 'next_due', 'signature_status',
        'document_link', 'notes', 'active',
    ];

    protected function casts(): array
    {
        return [
            'last_completed' => 'date',
            'next_due' => 'date',
            'interval_months' => 'integer',
            'active' => 'boolean',
        ];
    }

    public function requiredSigners(): HasMany
    {
        return $this->hasMany(RequiredSigner::class)->orderBy('sign_order');
    }

    public function completions(): HasMany
    {
        return $this->hasMany(Completion::class)->orderByDesc('completed_date');
    }

    public function signatureRequests(): HasMany
    {
        return $this->hasMany(SignatureRequest::class);
    }

    public function credentials(): HasMany
    {
        return $this->hasMany(Credential::class);
    }

    // --- Derived fields (computed live via the single source of truth) ---

    public function derived(): array
    {
        return app(ComplianceStatusService::class)->for($this);
    }

    public function status(): ComplianceStatus
    {
        return $this->derived()['status'];
    }

    public function daysUntilDue(): ?int
    {
        return $this->derived()['days_until_due'];
    }
}
