<?php

namespace App\Console\Commands;

use App\Services\ReminderService;
use Illuminate\Console\Command;

class SendComplianceReminders extends Command
{
    protected $signature = 'compliance:reminders';

    protected $description = 'Send due-date (30/7/0/-1) and signature (5/10 day) reminder emails. Idempotent.';

    public function handle(ReminderService $reminders): int
    {
        $due = $reminders->sendDueReminders();
        $sig = $reminders->sendSignatureReminders();

        $this->info('Due-date reminders sent: ' . json_encode($due));
        $this->info('Signature reminders sent: ' . json_encode($sig));

        return self::SUCCESS;
    }
}
