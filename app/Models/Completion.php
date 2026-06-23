<?php

namespace App\Models;

use App\Models\Concerns\BelongsToLab;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Completion extends Model
{
    use BelongsToLab;

    protected $fillable = [
        'lab_id', 'obligation_id', 'completed_date', 'document_link', 'drive_file_id', 'created_by',
    ];

    protected function casts(): array
    {
        return ['completed_date' => 'date'];
    }

    public function obligation(): BelongsTo
    {
        return $this->belongsTo(Obligation::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
