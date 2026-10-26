<?php

namespace Nreljed\MarketingModule\Services;

use Illuminate\Support\Facades\Log;
use Nreljed\MarketingModule\Contracts\EmailChannel;

class LogEmailChannel implements EmailChannel
{
    public function send(string $to, string $subject, string $html, array $headers = []): void
    {
        Log::info('marketing.email', [
            'to' => $to,
            'subject' => $subject,
            'headers' => $headers,
            'body' => $html,
        ]);
    }
}
