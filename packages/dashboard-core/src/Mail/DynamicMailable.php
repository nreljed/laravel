<?php

namespace Nreljed\DashboardCore\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Nreljed\DashboardCore\Services\EmailTemplateService;

class DynamicMailable extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        protected string $template,
        protected array $data = [],
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->data['subject'] ?? 'Notification',
        );
    }

    public function content(): Content
    {
        $html = app(EmailTemplateService::class)->renderWithLayout($this->template, $this->data);

        return new Content(
            htmlString: $html,
        );
    }
}
