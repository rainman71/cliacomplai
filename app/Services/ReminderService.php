<?php

namespace App\Services;

use App\Mail\ObligationDueReminder;
use App\Mail\OverdueDigest;
use App\Mail\SignatureReminder;
use App\Models\Lab;
use App\Models\Obligation;
use App\Models\ReminderSent;
use App\Models\SignatureRequest;
use App\Support\CurrentLab;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;

/**
 * Sends the due-date ladder (30/7/0/-1), signature reminders (5/10 day), and the weekly
 * overdue digest. Iterates every active lab, scoping each pass so obligations, idempotency,
 * and recipients all stay within that lab. Idempotent via reminders_sent.
 */
class ReminderService
{
    public function __construct(
        private ComplianceStatusService $status,
        private RecipientResolver $recipients,
        private CurrentLab $current,
    ) {}

    /** @return array<string, int> */
    public function sendDueReminders(?CarbonImmutable $today = null): array
    {
        $today = $today ?? CarbonImmutable::now();
        $sent = ['due_30' => 0, 'due_7' => 0, 'due_0' => 0, 'overdue_1' => 0];

        foreach (Lab::where('active', true)->get() as $lab) {
            $this->current->run($lab, function () use ($today, &$sent) {
                foreach (Obligation::where('active', true)->get() as $o) {
                    $d = $this->status->for($o, $today);
                    if ($d['next_due'] === null || $d['days_until_due'] === null) {
                        continue;
                    }

                    [$type, $groups] = $this->mostUrgentDue($o, $d['days_until_due']);
                    if (! $type) {
                        continue;
                    }

                    $dueKey = $d['next_due']->toDateString();
                    if ($this->alreadySent($o->id, $type, $dueKey)) {
                        continue;
                    }

                    $emails = $this->recipients->emails(...$groups);
                    if (empty($emails)) {
                        continue;
                    }

                    Mail::to($emails)->send(new ObligationDueReminder($o, $type, $dueKey, $d['days_until_due']));
                    $this->record($o->id, $type, $dueKey);
                    $sent[$type]++;
                }
            });
        }

        return $sent;
    }

    /** @return array<string, int> */
    public function sendSignatureReminders(?CarbonImmutable $today = null): array
    {
        $today = $today ?? CarbonImmutable::now();
        $sent = ['sig_reminder' => 0, 'sig_escalation' => 0];

        foreach (Lab::where('active', true)->get() as $lab) {
            $this->current->run($lab, function () use ($today, &$sent) {
                $open = SignatureRequest::with(['obligation', 'signers'])
                    ->whereIn('status', ['out_for_signature', 'partially_signed'])
                    ->whereNotNull('sent_date')->get();

                foreach ($open as $req) {
                    $daysPending = (int) CarbonImmutable::parse($req->sent_date)->startOfDay()->diffInDays($today->startOfDay());
                    $o = $req->obligation;
                    $cycleKey = CarbonImmutable::parse($req->sent_date)->toDateString();
                    $pending = $req->signers->whereIn('status', ['pending', 'not_sent']);
                    $pendingLabel = $pending->pluck('signer_name')->implode(', ');

                    if ($daysPending >= 10) {
                        $type = 'sig_escalation';
                        $escalation = true;
                        $groups = [$this->recipients->owner($o), $this->recipients->labDirector(), $this->recipients->complianceSpecialist()];
                    } elseif ($daysPending >= 5) {
                        $type = 'sig_reminder';
                        $escalation = false;
                        $groups = [$this->recipients->owner($o), ...$pending->map(fn ($s) => $this->recipients->forRole($s->signer_name))];
                    } else {
                        continue;
                    }

                    if ($this->alreadySent($o->id, $type, $cycleKey)) {
                        continue;
                    }
                    $emails = $this->recipients->emails(...$groups);
                    if (empty($emails)) {
                        continue;
                    }

                    Mail::to($emails)->send(new SignatureReminder($o, $req, $escalation, $daysPending, $pendingLabel));
                    $this->record($o->id, $type, $cycleKey);
                    $sent[$type]++;
                }
            });
        }

        return $sent;
    }

    /** Weekly overdue digest per lab. @return int total items across labs */
    public function sendOverdueDigest(?CarbonImmutable $today = null): int
    {
        $today = $today ?? CarbonImmutable::now();
        $total = 0;

        foreach (Lab::where('active', true)->get() as $lab) {
            $this->current->run($lab, function () use ($today, &$total) {
                $items = collect();
                foreach (Obligation::where('active', true)->get() as $o) {
                    $d = $this->status->for($o, $today);
                    if ($d['days_until_due'] !== null && $d['days_until_due'] < 0) {
                        $items->push(['obligation' => $o, 'days' => $d['days_until_due']]);
                    }
                }

                if ($items->isEmpty()) {
                    return;
                }

                $emails = $this->recipients->emails(
                    $this->recipients->complianceSpecialist(),
                    $this->recipients->labDirector(),
                );
                if (empty($emails)) {
                    return;
                }

                Mail::to($emails)->send(new OverdueDigest($items->sortBy('days')->values()));
                $total += $items->count();
            });
        }

        return $total;
    }

    /** @return array{0: ?string, 1: array<int, \Illuminate\Support\Collection>} */
    private function mostUrgentDue(Obligation $o, int $days): array
    {
        if ($days < 0) {
            return ['overdue_1', [$this->recipients->labDirector(), $this->recipients->complianceSpecialist()]];
        }
        if ($days <= 0) {
            return ['due_0', [$this->recipients->owner($o), $this->recipients->labDirector(), $this->recipients->complianceSpecialist()]];
        }
        if ($days <= 7) {
            return ['due_7', [$this->recipients->owner($o), $this->recipients->labDirector()]];
        }
        if ($days <= 30) {
            return ['due_30', [$this->recipients->owner($o)]];
        }

        return [null, []];
    }

    private function alreadySent(int $obligationId, string $type, string $dueDate): bool
    {
        return ReminderSent::where('obligation_id', $obligationId)
            ->where('reminder_type', $type)
            ->whereDate('due_date', $dueDate)
            ->exists();
    }

    private function record(int $obligationId, string $type, string $dueDate): void
    {
        ReminderSent::create([
            'obligation_id' => $obligationId,
            'reminder_type' => $type,
            'due_date' => $dueDate,
            'sent_at' => now(),
        ]);
    }
}
