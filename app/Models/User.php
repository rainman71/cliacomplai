<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;

#[Fillable(['name', 'email', 'password', 'google_sub', 'active', 'is_super_admin'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'active' => 'boolean',
            'is_super_admin' => 'boolean',
        ];
    }

    /** All roles => human label. */
    public const ROLES = [
        'admin' => 'Admin',
        'lab_director' => 'Lab Director',
        'lab_director_designee' => 'Lab Director Designee',
        'compliance_specialist' => 'Compliance Specialist',
        'tech_supervisor' => 'Technical Supervisor',
        'general_supervisor' => 'General Supervisor',
        'safety_officer' => 'Safety Officer',
        'tech_staff' => 'Technical Staff',
    ];

    /** Roles allowed to manage users/access within a lab. */
    public const MANAGER_ROLES = ['admin', 'lab_director', 'compliance_specialist'];

    /** Roles allowed to edit the register / drive the signature workflow. A Lab Director Designee
     *  acts for the director on compliance work (edit + sign) but not user administration. */
    public const EDITOR_ROLES = ['admin', 'lab_director', 'lab_director_designee', 'compliance_specialist', 'tech_supervisor', 'general_supervisor'];

    // --- Relationships ---

    public function memberships(): HasMany
    {
        return $this->hasMany(LabUser::class);
    }

    public function labs(): BelongsToMany
    {
        return $this->belongsToMany(Lab::class, 'lab_user')->withPivot('active')->withTimestamps();
    }

    // --- Multi-tenant access helpers ---

    public function isSuperAdmin(): bool
    {
        return (bool) $this->is_super_admin;
    }

    /** Labs this user may enter (all active labs for a super admin). */
    public function accessibleLabs(): Collection
    {
        if ($this->isSuperAdmin()) {
            return Lab::where('active', true)->orderBy('name')->get();
        }

        return Lab::where('active', true)
            ->whereHas('memberships', fn ($q) => $q->where('user_id', $this->id)->where('active', true))
            ->orderBy('name')
            ->get();
    }

    public function membershipFor(Lab|int $lab): ?LabUser
    {
        return $this->memberships()->with('roles')
            ->where('lab_id', $lab instanceof Lab ? $lab->id : $lab)
            ->first();
    }

    /** The set of roles this user holds at a lab (all roles for a super admin). */
    public function rolesForLab(Lab|int $lab): array
    {
        if ($this->isSuperAdmin()) {
            return array_keys(self::ROLES);
        }
        $membership = $this->membershipFor($lab);

        return ($membership && $membership->active) ? $membership->roleNames() : [];
    }

    public function hasLabAccess(Lab|int $lab): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }
        $membership = $this->membershipFor($lab);

        return $membership !== null && $membership->active;
    }

    public function canEditLab(Lab|int $lab): bool
    {
        return $this->isSuperAdmin() || count(array_intersect($this->rolesForLab($lab), self::EDITOR_ROLES)) > 0;
    }

    public function canManageLab(Lab|int $lab): bool
    {
        return $this->isSuperAdmin() || count(array_intersect($this->rolesForLab($lab), self::MANAGER_ROLES)) > 0;
    }

    /** Human-readable role list for a lab (for headers/badges). */
    public function roleListForLab(Lab|int $lab): string
    {
        if ($this->isSuperAdmin()) {
            return 'Super Admin';
        }
        $labels = array_map(fn ($r) => self::ROLES[$r] ?? $r, $this->rolesForLab($lab));

        return $labels ? implode(', ', $labels) : 'No role';
    }
}
