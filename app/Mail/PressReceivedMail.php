<?php

namespace App\Mail;

use App\Models\PressRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PressReceivedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public PressRequest $press)
    {
        $this->press->loadMissing('meet');
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: '【'.$this->press->meet->name.'】取材・撮影のお申込みを受け付けました');
    }

    public function content(): Content
    {
        return new Content(text: 'mail.press-received');
    }
}
