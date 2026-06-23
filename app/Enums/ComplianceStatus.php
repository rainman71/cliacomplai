<?php

namespace App\Enums;

/**
 * The auto-calculated status of an obligation. Mirrors the rule in the plan (§2):
 *   no next_due            -> SET_DATES
 *   days_until_due < 0     -> OVERDUE
 *   0..30                  -> DUE_30
 *   31..60                 -> DUE_60
 *   > 60                   -> ON_TRACK
 */
enum ComplianceStatus: string
{
    case SET_DATES = 'set_dates';
    case OVERDUE = 'overdue';
    case DUE_30 = 'due_30';
    case DUE_60 = 'due_60';
    case ON_TRACK = 'on_track';

    /** Human-readable label for the dashboard. */
    public function label(): string
    {
        return match ($this) {
            self::SET_DATES => 'Set dates',
            self::OVERDUE => 'OVERDUE',
            self::DUE_30 => 'Due ≤30 days',
            self::DUE_60 => 'Due ≤60 days',
            self::ON_TRACK => 'On track',
        };
    }

    /** Tailwind-ish color key used by the UI (overview cards / row backgrounds). */
    public function color(): string
    {
        return match ($this) {
            self::SET_DATES => 'gray',
            self::OVERDUE => 'red',
            self::DUE_30 => 'amber',
            self::DUE_60 => 'yellow',
            self::ON_TRACK => 'green',
        };
    }
}
