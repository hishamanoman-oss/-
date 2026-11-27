<?php

declare(strict_types=1);

namespace App\Domain\Reports\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class ScheduledReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $reportName,
        public readonly string $reportTitle,
        public readonly string $subtitle,
        public readonly string $tenantName,
        public readonly string $fileName,
        public readonly string $mimeType,
        public readonly string $binaryPayload,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "{$this->tenantName} — {$this->reportName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.scheduled-report',
        );
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn (): string => $this->binaryPayload, $this->fileName)
                ->withMime($this->mimeType),
        ];
    }
}
