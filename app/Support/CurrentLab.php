<?php

namespace App\Support;

use App\Models\Lab;

/**
 * Holds the active lab for the current request/process. Bound as a singleton; set by the
 * EnsureLabMember middleware (web) or explicitly by console jobs (e.g. reminders loop).
 * The BelongsToLab global scope reads this to filter every tenant query.
 */
class CurrentLab
{
    private ?Lab $lab = null;

    public function set(?Lab $lab): void
    {
        $this->lab = $lab;
    }

    public function get(): ?Lab
    {
        return $this->lab;
    }

    public function id(): ?int
    {
        return $this->lab?->id;
    }

    public function isSet(): bool
    {
        return $this->lab !== null;
    }

    /** Run a callback scoped to a given lab, restoring the previous lab afterward. */
    public function run(Lab $lab, callable $callback): mixed
    {
        $previous = $this->lab;
        $this->lab = $lab;

        try {
            return $callback();
        } finally {
            $this->lab = $previous;
        }
    }
}
