<?php

declare(strict_types=1);

namespace Atrium\Admin\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Письмо с кодом подтверждения входа в админку.
 *
 * Код выносим в тему письма: в списке писем его видно без открытия, а
 * набирать всё равно вручную.
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
            subject: $this->code.' — код для входа в админку',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'atrium::emails.two-factor-code',
        );
    }
}
