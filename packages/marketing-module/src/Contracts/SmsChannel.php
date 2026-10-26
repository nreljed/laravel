<?php

namespace Nreljed\MarketingModule\Contracts;

/**
 * A channel that can deliver an SMS message.
 */
interface SmsChannel
{
    public function send(string $to, string $message): void;
}
