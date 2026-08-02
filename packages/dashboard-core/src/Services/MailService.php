<?php

namespace Nreljed\DashboardCore\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Nreljed\DashboardCore\Jobs\SendEmail;
use Nreljed\DashboardCore\Mail\DynamicMailable;

class MailService
{
    public function __construct(protected EmailTemplateService $templateService) {}

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

        Mail::to($to)->send(new DynamicMailable($template, $data));

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
