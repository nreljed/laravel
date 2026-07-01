<?php

namespace App\Jobs;

use App\Mail\DynamicMailable;
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

    public function handle(): void
    {
        try {
            $data = array_merge($this->data, ['subject' => $this->subject]);

            Mail::to($this->to)->send(new DynamicMailable($this->template, $data));
        } catch (\Throwable $e) {
            Log::error('[SendEmail] Failed to send email to: '.$this->to.' | Subject: '.$this->subject.' | Error: '.$e->getMessage());
        }
    }
}
