<?php

namespace App\Models;

use App\Models\Concerns\BelongsToLab;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use BelongsToLab;

    protected $table = 'audit_log';

    // Append-only: no updated_at, and we never edit rows after insert.
    public $timestamps = false;

    protected $fillable = [
        'lab_id', 'entity_type', 'entity_id', 'field', 'old_value', 'new_value',
        'action', 'changed_by', 'changed_at',
    ];

    protected function casts(): array
    {
        return ['changed_at' => 'datetime'];
    }

    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
