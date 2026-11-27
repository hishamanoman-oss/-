<?php

declare(strict_types=1);

namespace App\Domain\Alerts\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class AlertRuleTriggeredMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $alertTitle,
        public readonly string $alertBody,
        public readonly string $severity,
        public readonly string $tenantName,
        public readonly ?string $actionUrl = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '['.strtoupper($this->severity)."] {$this->tenantName} — {$this->alertTitle}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.alert-rule-triggered',
        );
    }
}
