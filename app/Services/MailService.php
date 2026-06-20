<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class MailService
{
    protected EmailTemplateService $templateService;

    public function __construct(EmailTemplateService $templateService)
    {
        $this->templateService = $templateService;
    }

    public function send(string $to, string $subject, string $template, array $data = []): bool
    {
        try {
            $html = $this->templateService->renderWithLayout($template, $data);

            Mail::html($html, function ($message) use ($to, $subject) {
                $message->to($to)
                    ->subject($subject);
            });

            return true;
        } catch (\Throwable $e) {
            Log::error('[MailService] Failed to send email to: ' . $to . ' | Subject: ' . $subject . ' | Error: ' . $e->getMessage());
            return false;
        }
    }

    public function sendRaw(string $to, string $subject, string $message): bool
    {
        try {
            Mail::raw($message, function ($msg) use ($to, $subject) {
                $msg->to($to)->subject($subject);
            });
            return true;
        } catch (\Throwable $e) {
            Log::error('[MailService] Failed to send raw email to: ' . $to . ' | Error: ' . $e->getMessage());
            return false;
        }
    }
}
