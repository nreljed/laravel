<?php

namespace Nreljed\MarketingModule\Contracts;

/**
 * A channel that can deliver a WhatsApp message.
 */
interface WhatsAppChannel
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function send(string $to, string $message, array $metadata = []): void;
}
