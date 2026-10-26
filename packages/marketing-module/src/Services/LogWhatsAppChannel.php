<?php

namespace Nreljed\MarketingModule\Services;

use Illuminate\Support\Facades\Log;
use Nreljed\MarketingModule\Contracts\WhatsAppChannel;

class LogWhatsAppChannel implements WhatsAppChannel
{
    public function send(string $to, string $message, array $metadata = []): void
    {
        Log::info('marketing.whatsapp', [
            'to' => $to,
            'message' => $message,
            'metadata' => $metadata,
        ]);
    }
}
