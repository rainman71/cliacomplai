<?php

namespace App\Models;

use App\Models\Concerns\BelongsToLab;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Credential extends Model
{
    use BelongsToLab;

    protected $fillable = [
        'lab_id', 'obligation_id', 'person_name', 'credential_type', 'expiry_date', 'document_link',
    ];

    protected function casts(): array
    {
        return ['expiry_date' => 'date'];
    }

    public function obligation(): BelongsTo
    {
        return $this->belongsTo(Obligation::class);
    }
}
