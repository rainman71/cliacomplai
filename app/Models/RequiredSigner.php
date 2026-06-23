<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequiredSigner extends Model
{
    protected $fillable = [
        'obligation_id', 'sign_order', 'signer_role', 'signer_user_id',
    ];

    public function obligation(): BelongsTo
    {
        return $this->belongsTo(Obligation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signer_user_id');
    }
}
