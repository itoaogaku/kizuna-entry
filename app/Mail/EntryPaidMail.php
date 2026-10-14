<?php

namespace App\Mail;

use App\Models\Entry;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EntryPaidMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Entry $entry)
    {
        $this->entry->loadMissing('meet', 'user', 'items.athlete', 'items.event');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '【'.$this->entry->meet->name.'】エントリーと入金を受け付けました',
        );
    }

    public function content(): Content
    {
        return new Content(text: 'mail.entry-paid');
    }
}
