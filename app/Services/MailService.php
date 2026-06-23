<?php

namespace App\Services;

use App\Jobs\SendEmail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class MailService
{
    protected EmailTemplateService $templateService;

    public function __construct(EmailTemplateService $templateService)
    {
        $this->templateService = $templateService;
    }

    /**
     * Send email asynchronously via the queue.
     */
    public function send(string $to, string $subject, string $template, array $data = []): bool
    {
        try {
            $data['subject'] = $subject;

            SendEmail::dispatch($to, $subject, $template, $data);

            return true;
        } catch (\Throwable $e) {
            Log::error('[MailService] Failed to queue email to: '.$to.' | Subject: '.$subject.' | Error: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Send email synchronously (used for test emails).
     * Throws on failure so callers can display the real error.
     */
    public function sendNow(string $to, string $subject, string $template, array $data = []): bool
    {
        $data['subject'] = $subject;
        $html = $this->templateService->renderWithLayout($template, $data);

        Mail::html($html, function ($message) use ($to, $subject) {
            $message->to($to)
                ->subject($subject);
        });

        return true;
    }

    public function sendRaw(string $to, string $subject, string $message): bool
    {
        try {
            Mail::raw($message, function ($msg) use ($to, $subject) {
                $msg->to($to)->subject($subject);
            });

            return true;
        } catch (\Throwable $e) {
            Log::error('[MailService] Failed to send raw email to: '.$to.' | Error: '.$e->getMessage());

            return false;
        }
    }
}
