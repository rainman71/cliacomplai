<?php

namespace App\Mail;

use App\Models\Obligation;
use App\Models\SignatureRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SignatureReminder extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Obligation $obligation,
        public SignatureRequest $request,
        public bool $escalation,
        public int $daysPending,
        public string $pendingSigners,
    ) {}

    public function envelope(): Envelope
    {
        $code = $this->obligation->code;

        $subject = $this->escalation
            ? "[CLIA] ESCALATION: {$code} signature outstanding {$this->daysPending} days"
            : "[CLIA] Reminder: {$code} awaiting signature ({$this->daysPending} days)";

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.signature-reminder');
    }
}
