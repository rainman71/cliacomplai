<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A lab management company — the tenant tier above labs. Owns many labs and (from Phase 2) its
 * own curated P&P/obligation template that seeds each lab beneath it.
 */
class ManagementCompany extends Model
{
    protected $fillable = ['name', 'slug', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function labs(): HasMany
    {
        return $this->hasMany(Lab::class);
    }
}
