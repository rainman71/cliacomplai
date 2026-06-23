<?php

namespace App\Services;

use App\Models\Obligation;
use App\Models\User;
use App\Support\CurrentLab;
use Illuminate\Support\Collection;

/**
 * Resolves the human role strings on obligations/signers to actual users — scoped to the
 * ACTIVE lab. A user matches a role if their lab role SET includes it (any-match), so someone
 * wearing multiple hats is reached under each one. Never resolves across labs.
 */
class RecipientResolver
{
    private const ROLE_MAP = [
        'lab director' => 'lab_director',
        'lab director / designee' => 'lab_director',
        'tech supervisor' => 'tech_supervisor',
        'technical supervisor' => 'tech_supervisor',
        'general supervisor' => 'general_supervisor',
        'compliance specialist' => 'compliance_specialist',
        'safety officer' => 'safety_officer',
        'technical staff' => 'tech_staff',
        'technical staff member' => 'tech_staff',
    ];

    /** @return Collection<int, User> */
    public function forRole(?string $humanRole): Collection
    {
        $enum = self::ROLE_MAP[strtolower(trim((string) $humanRole))] ?? null;
        $labId = app(CurrentLab::class)->id();

        if (! $enum || ! $labId) {
            return collect();
        }

        return User::where('active', true)
            ->whereHas('memberships', fn ($m) => $m->where('lab_id', $labId)->where('active', true)
                ->whereHas('roles', fn ($r) => $r->where('role', $enum)))
            ->get();
    }

    /** @return Collection<int, User> */
    public function owner(Obligation $obligation): Collection
    {
        return $this->forRole($obligation->owner_role);
    }

    /** @return Collection<int, User> */
    public function labDirector(): Collection
    {
        return $this->forRole('Lab Director');
    }

    /** @return Collection<int, User> */
    public function complianceSpecialist(): Collection
    {
        return $this->forRole('Compliance Specialist');
    }

    /**
     * @param  Collection<int, User>  ...$groups
     * @return array<int, string>
     */
    public function emails(Collection ...$groups): array
    {
        return collect($groups)->flatten()->pluck('email')->filter()->unique()->values()->all();
    }
}
