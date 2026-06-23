<?php

namespace App\Console\Commands;

use App\Services\ReminderService;
use Illuminate\Console\Command;

class SendOverdueDigest extends Command
{
    protected $signature = 'compliance:overdue-digest';

    protected $description = 'Send the weekly overdue digest to Compliance Specialist + Lab Director.';

    public function handle(ReminderService $reminders): int
    {
        $count = $reminders->sendOverdueDigest();

        $this->info($count > 0
            ? "Overdue digest sent ({$count} item(s))."
            : 'No overdue items — digest not sent.');

        return self::SUCCESS;
    }
}
