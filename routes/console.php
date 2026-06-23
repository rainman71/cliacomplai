<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Daily: pull signed evidence from Drive and advance matching obligations, so the register is
// in sync before reminders/overdue calculations run. No-ops if Drive isn't configured.
Schedule::command('compliance:ingest-evidence --apply')->dailyAt('06:30')->withoutOverlapping();

// Daily reminder ladder (due 30/7/0/-1) + signature reminders (5/10 day).
Schedule::command('compliance:reminders')->dailyAt('07:00');

// Weekly overdue digest, Mondays.
Schedule::command('compliance:overdue-digest')->weeklyOn(1, '07:30');
