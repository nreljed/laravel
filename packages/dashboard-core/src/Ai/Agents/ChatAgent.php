<?php

namespace Nreljed\DashboardCore\Ai\Agents;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;
use Laravel\Ai\Responses\AgentResponse;
use Nreljed\DashboardCore\Ai\Agents\Traits\ResolvesUserProvider;
use Nreljed\DashboardCore\Ai\Concerns\LogsAiUsage;
use Nreljed\DashboardCore\Models\User;

class ChatAgent implements Agent
{
    use LogsAiUsage, Promptable, ResolvesUserProvider;

    /**
     * System instructions for the chat assistant.
     */
    public function instructions(): string
    {
        return <<<'INSTRUCTIONS'
You are a helpful AI assistant embedded in the Cost Breaker 360 dashboard.
You help users with:
- Cost management questions and analysis
- Dashboard navigation and feature explanations
- Data interpretation and reporting
- General productivity and workflow advice

Be concise, professional, and actionable. If you don't know something about the application, say so honestly rather than guessing.
INSTRUCTIONS;
    }

    /**
     * Send a chat message and get a text response.
     */
    public function chat(User $user, string $message, ?string $conversationId = null): string
    {
        $this->forUser($user);

        $this->injectProviderKey();

        $response = $this->prompt($message);

        $this->logUsage($user, $response);

        return (string) $response;
    }

    /**
     * Log usage for a chat interaction.
     */
    protected function logUsage(User $user, AgentResponse $response): void
    {
        $resolved = $this->getResolvedProvider();
        $this->logAiUsage(
            user: $user,
            provider: $resolved['driver'],
            model: $resolved['model'] ?? 'unknown',
            inputTokens: $response->usage->promptTokens,
            outputTokens: $response->usage->completionTokens,
        );
    }
}
