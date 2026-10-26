<?php

namespace Nreljed\MarketingModule\Contracts;

/**
 * A channel that can deliver an email message.
 *
 * Provider differences are normalised here. The broadcast service never learns
 * a provider's API shape.
 */
interface EmailChannel
{
    /**
     * @param  array<string, string>  $headers
     */
    public function send(string $to, string $subject, string $html, array $headers = []): void;
}
