<?php

namespace Nreljed\DashboardCore\Ai\Exceptions;

use RuntimeException;

/**
 * Thrown when a user attempts an AI operation but has no provider key configured.
 */
class MissingAiProviderException extends RuntimeException
{
    public function __construct(string $provider = 'openai')
    {
        $providerName = ucfirst($provider);

        parent::__construct(
            "No AI API key configured for {$providerName}. "
            ."Please add your {$providerName} API key in Account â†’ AI Keys."
        );
    }
}
