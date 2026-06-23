<?php

namespace App\Models\Concerns;

use App\Models\Lab;
use App\Support\CurrentLab;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Scopes a model to the active lab. While a current lab is set, all reads are filtered to it
 * and all creates are stamped with it — the safety net against cross-tenant leakage. When no
 * lab is set (console, seeders, super-admin portfolio), no implicit filtering is applied;
 * those contexts scope explicitly via forLab()/allLabs() or CurrentLab::run().
 */
trait BelongsToLab
{
    protected static function bootBelongsToLab(): void
    {
        static::addGlobalScope('lab', function (Builder $builder) {
            $current = app(CurrentLab::class);
            if ($current->id() !== null) {
                $builder->where($builder->getModel()->getTable() . '.lab_id', $current->id());
            }
        });

        static::creating(function ($model) {
            if (empty($model->lab_id) && ($id = app(CurrentLab::class)->id()) !== null) {
                $model->lab_id = $id;
            }
        });
    }

    public function lab(): BelongsTo
    {
        return $this->belongsTo(Lab::class);
    }

    public function scopeForLab(Builder $query, int $labId): Builder
    {
        return $query->withoutGlobalScope('lab')->where($this->getTable() . '.lab_id', $labId);
    }

    public function scopeAllLabs(Builder $query): Builder
    {
        return $query->withoutGlobalScope('lab');
    }
}
