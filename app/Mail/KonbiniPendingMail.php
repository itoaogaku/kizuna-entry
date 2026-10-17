<?php

namespace App\Mail;

use App\Models\Entry;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * コンビニ払いの払込番号が発行されたときの案内。申込日のうちの支払いをお願いする。
 */
class KonbiniPendingMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Entry $entry)
    {
        $this->entry->loadMissing('meet', 'user', 'items.athlete', 'items.event');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '【'.$this->entry->meet->name.'】コンビニでのお支払いをお願いします（本日中）',
        );
    }

    public function content(): Content
    {
        return new Content(text: 'mail.konbini-pending');
    }
}
