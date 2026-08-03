<?php

namespace Nreljed\DashboardCore\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Nreljed\DashboardCore\Models\RecoveryToken;

class RecoveryMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $plaintextToken;

    public function __construct(
        public RecoveryToken $token,
    ) {
        $this->plaintextToken = $token->getPlaintext();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: config('app.name', 'Cost Breaker 360').' — Maintenance Recovery Link',
        );
    }

    public function content(): Content
    {
        $url = route('maintenance.recovery.claim', $this->plaintextToken);

        return new Content(
            htmlString: $this->buildHtml($url),
        );
    }

    private function buildHtml(string $url): string
    {
        $appName = config('app.name', 'Cost Breaker 360');

        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head><meta charset="utf-8"></head>
        <body style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:#f8fafc;padding:40px;color:#1e293b;">
        <div style="max-width:520px;margin:0 auto;background:#fff;border-radius:12px;padding:32px;box-shadow:0 1px 3px rgba(0,0,0,0.1);">
        <h1 style="font-size:20px;margin:0 0 16px;">{$appName} — Maintenance Recovery</h1>
        <p style="color:#64748b;line-height:1.6;margin:0 0 24px;">Your application is in maintenance mode. Use the link below to access the login page. This link is valid for 24 hours and can only be used once.</p>
        <a href="{$url}" style="display:inline-block;background:#3b82f6;color:#fff;text-decoration:none;padding:12px 24px;border-radius:8px;font-weight:600;">Recover Access</a>
        <p style="color:#94a3b8;font-size:13px;margin:24px 0 0;">If you did not request this, you can safely ignore this email.</p>
        </div>
        </body>
        </html>
        HTML;
    }
}
