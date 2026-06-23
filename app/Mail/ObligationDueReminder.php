<?php

namespace App\Mail;

use App\Models\Obligation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ObligationDueReminder extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Obligation $obligation,
        public string $type,    // due_30 | due_7 | due_0 | overdue_1
        public string $nextDue,
        public int $days,
    ) {}

    public function envelope(): Envelope
    {
        $code = $this->obligation->code;

        $headline = match ($this->type) {
            'due_30' => "{$code} due in 30 days",
            'due_7' => "{$code} due in 7 days",
            'due_0' => "{$code} due TODAY",
            'overdue_1' => "{$code} is OVERDUE",
            default => "{$code} reminder",
        };

        return new Envelope(subject: "[CLIA] {$headline} — {$this->obligation->name}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.obligation-due');
    }
}
