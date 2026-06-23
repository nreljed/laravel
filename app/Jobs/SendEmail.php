<?php

namespace App\Jobs;

use App\Services\EmailTemplateService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendEmail implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected string $to,
        protected string $subject,
        protected string $template,
        protected array $data = [],
    ) {}

    public function handle(EmailTemplateService $templateService): void
    {
        try {
            $data = array_merge($this->data, ['subject' => $this->subject]);
            $html = $templateService->renderWithLayout($this->template, $data);

            Mail::html($html, function ($message) {
                $message->to($this->to)
                    ->subject($this->subject);
            });
        } catch (\Throwable $e) {
            Log::error('[SendEmail] Failed to send email to: ' . $this->to . ' | Subject: ' . $this->subject . ' | Error: ' . $e->getMessage());
        }
    }
}
