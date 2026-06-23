<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LabUserRole extends Model
{
    protected $table = 'lab_user_role';

    public $timestamps = false;

    protected $fillable = ['lab_user_id', 'role'];

    public function membership(): BelongsTo
    {
        return $this->belongsTo(LabUser::class, 'lab_user_id');
    }
}
