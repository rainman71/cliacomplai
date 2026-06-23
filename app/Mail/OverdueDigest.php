<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class OverdueDigest extends Mailable
{
    use Queueable, SerializesModels;

    /** @param Collection<int, array{obligation: \App\Models\Obligation, days: int}> $items */
    public function __construct(public Collection $items) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "[CLIA] Weekly overdue digest — {$this->items->count()} item(s)");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.overdue-digest');
    }
}
