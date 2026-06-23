<?php

namespace App\Models;

use App\Models\Concerns\BelongsToLab;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SignatureRequest extends Model
{
    use BelongsToLab;

    protected $fillable = [
        'lab_id', 'obligation_id', 'completion_id', 'provider', 'envelope_id',
        'sent_date', 'deadline', 'status',
    ];

    protected function casts(): array
    {
        return [
            'sent_date' => 'date',
            'deadline' => 'date',
        ];
    }

    public function obligation(): BelongsTo
    {
        return $this->belongsTo(Obligation::class);
    }

    public function completion(): BelongsTo
    {
        return $this->belongsTo(Completion::class);
    }

    public function signers(): HasMany
    {
        return $this->hasMany(SignatureRequestSigner::class);
    }

    /** "1 of 3 signed" style progress. */
    public function signedCount(): int
    {
        return $this->signers->where('status', 'signed')->count();
    }

    public function totalSigners(): int
    {
        return $this->signers->count();
    }
}
