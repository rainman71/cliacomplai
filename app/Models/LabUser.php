<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A user's membership in a lab. Carries a per-lab active flag and a SET of roles
 * (lab_user_role) — one person may hold several roles at one lab.
 */
class LabUser extends Model
{
    protected $table = 'lab_user';

    protected $fillable = ['lab_id', 'user_id', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function lab(): BelongsTo
    {
        return $this->belongsTo(Lab::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function roles(): HasMany
    {
        return $this->hasMany(LabUserRole::class);
    }

    /** @return array<int, string> */
    public function roleNames(): array
    {
        return $this->roles->pluck('role')->all();
    }

    public function syncRoles(array $roles): void
    {
        $roles = array_values(array_unique(array_intersect($roles, array_keys(User::ROLES))));
        $this->roles()->delete();
        foreach ($roles as $role) {
            $this->roles()->create(['role' => $role]);
        }
    }
}
