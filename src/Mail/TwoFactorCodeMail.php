<?php

declare(strict_types=1);

namespace Atrium\Admin\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The e-mail with the admin sign-in confirmation code.
 *
 * The code goes into the subject: it is visible in the inbox list without
 * opening the message, and it is typed by hand anyway.
 */
class TwoFactorCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $code,
        public int $ttlMinutes,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('atrium::auth.mail.subject', ['code' => $this->code]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'atrium::emails.two-factor-code',
        );
    }
}
