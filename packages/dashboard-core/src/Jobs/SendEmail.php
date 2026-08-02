<?php

namespace Nreljed\DashboardCore\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Nreljed\DashboardCore\Mail\DynamicMailable;

class SendEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        protected string $to,
        protected string $subject,
        protected string $template,
        protected array $data = [],
    ) {}

    public function handle(): void
    {
        $data = array_merge($this->data, ['subject' => $this->subject]);

        Mail::to($this->to)->send(new DynamicMailable($this->template, $data));
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('[SendEmail] Job failed permanently for: '.$this->to.' | Error: '.$exception->getMessage());
    }
}
