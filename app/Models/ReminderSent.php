<?php

namespace App\Models;

use App\Models\Concerns\BelongsToLab;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReminderSent extends Model
{
    use BelongsToLab;

    protected $table = 'reminders_sent';

    public $timestamps = false;

    protected $fillable = [
        'lab_id', 'obligation_id', 'reminder_type', 'due_date', 'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'sent_at' => 'datetime',
        ];
    }

    public function obligation(): BelongsTo
    {
        return $this->belongsTo(Obligation::class);
    }
}
