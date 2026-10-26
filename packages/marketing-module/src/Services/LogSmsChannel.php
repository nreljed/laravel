<?php

namespace Nreljed\MarketingModule\Services;

use Illuminate\Support\Facades\Log;
use Nreljed\MarketingModule\Contracts\SmsChannel;

class LogSmsChannel implements SmsChannel
{
    public function send(string $to, string $message): void
    {
        Log::info('marketing.sms', [
            'to' => $to,
            'message' => $message,
        ]);
    }
}
