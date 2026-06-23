<?php

namespace App\Services;

use App\Enums\ComplianceStatus;
use App\Models\Obligation;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Single source of truth for the derived compliance fields.
 *
 * Every dashboard view computes status through this service so the rule lives in
 * exactly one place. Derived values are computed at read time and never stored
 * stale (next_due is the one value safe to cache, since it only changes when
 * last_completed changes).
 */
class ComplianceStatusService
{
    /**
     * next_due = last_completed + interval_months. Null if either input is missing.
     */
    public function nextDue(?CarbonInterface $lastCompleted, ?int $intervalMonths): ?CarbonImmutable
    {
        if ($lastCompleted === null || $intervalMonths === null) {
            return null;
        }

        return CarbonImmutable::parse($lastCompleted)->addMonths($intervalMonths);
    }

    /**
     * Whole days from `today` until next_due (negative = overdue). Null if no next_due.
     */
    public function daysUntilDue(?CarbonInterface $nextDue, ?CarbonInterface $today = null): ?int
    {
        if ($nextDue === null) {
            return null;
        }

        $today = CarbonImmutable::parse($today ?? CarbonImmutable::now())->startOfDay();

        // diffInDays(false) keeps the sign: past dates come back negative.
        return (int) $today->diffInDays(CarbonImmutable::parse($nextDue)->startOfDay(), false);
    }

    /**
     * Map days-until-due to a status. `hasNextDue` distinguishes "no baseline yet"
     * (Set dates) from a real schedule.
     */
    public function status(?int $daysUntilDue, bool $hasNextDue): ComplianceStatus
    {
        if (! $hasNextDue || $daysUntilDue === null) {
            return ComplianceStatus::SET_DATES;
        }

        return match (true) {
            $daysUntilDue < 0 => ComplianceStatus::OVERDUE,
            $daysUntilDue <= 30 => ComplianceStatus::DUE_30,
            $daysUntilDue <= 60 => ComplianceStatus::DUE_60,
            default => ComplianceStatus::ON_TRACK,
        };
    }

    /**
     * Whole days since the obligation was last touched/verified in-app. Negative is clamped by
     * callers; null when never recorded.
     */
    public function daysSinceVerified(?CarbonInterface $verifiedAt, ?CarbonInterface $today = null): ?int
    {
        if ($verifiedAt === null) {
            return null;
        }

        $today = CarbonImmutable::parse($today ?? CarbonImmutable::now())->startOfDay();

        return (int) CarbonImmutable::parse($verifiedAt)->startOfDay()->diffInDays($today, false);
    }

    /**
     * "Stale" = nobody has touched this obligation within its review window, so a green status
     * may no longer reflect reality. Window = the obligation's interval (months → days), or one
     * year for event-driven items with no interval.
     */
    public function isStale(?int $daysSinceVerified, ?int $intervalMonths): bool
    {
        if ($daysSinceVerified === null) {
            return false;
        }

        $windowDays = $intervalMonths ? $intervalMonths * 31 : 365;

        return $daysSinceVerified > $windowDays;
    }

    /**
     * Compute all derived fields for one obligation at once.
     *
     * @return array{next_due: ?CarbonImmutable, days_until_due: ?int, status: ComplianceStatus, days_since_verified: ?int, stale: bool}
     */
    public function for(Obligation $obligation, ?CarbonInterface $today = null): array
    {
        $nextDue = $this->nextDue($obligation->last_completed, $obligation->interval_months);
        $days = $this->daysUntilDue($nextDue, $today);
        $sinceVerified = $this->daysSinceVerified($obligation->updated_at, $today);

        return [
            'next_due' => $nextDue,
            'days_until_due' => $days,
            'status' => $this->status($days, $nextDue !== null),
            'days_since_verified' => $sinceVerified,
            'stale' => $this->isStale($sinceVerified, $obligation->interval_months),
        ];
    }
}
