<?php

namespace App\Models;

use App\Models\Concerns\BelongsToLab;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormResponse extends Model
{
    use BelongsToLab;

    protected $fillable = [
        'lab_id', 'obligation_id', 'form_code', 'title', 'answers',
        'status', 'completed_date', 'completed_by', 'drive_file_id', 'document_link',
    ];

    protected function casts(): array
    {
        return [
            'answers' => 'array',
            'completed_date' => 'date',
        ];
    }

    public function obligation(): BelongsTo
    {
        return $this->belongsTo(Obligation::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}
