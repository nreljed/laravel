<?php

namespace Nreljed\Platform\Ai\Agents;

use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Promptable;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\StreamableAgentResponse;
use Nreljed\Platform\Ai\Agents\Traits\ResolvesUserProvider;
use Nreljed\Platform\Ai\Concerns\EnforcesAiCaps;
use Nreljed\Platform\Ai\Concerns\LogsAiUsage;
use Nreljed\Platform\Models\User;

class ChatAgent implements Agent, Conversational
{
    use EnforcesAiCaps, LogsAiUsage, Promptable, RemembersConversations, ResolvesUserProvider {
        ResolvesUserProvider::forUser insteadof RemembersConversations;
    }

    /**
     * System instructions for the chat assistant.
     */
    public function instructions(): string
    {
        return <<<'INSTRUCTIONS'
You are a helpful AI assistant embedded in the application dashboard.
You help users with:
- Cost management questions and analysis
- Dashboard navigation and feature explanations
- Data interpretation and reporting
- General productivity and workflow advice

Be concise, professional, and actionable. If you don't know something about the application, say so honestly rather than guessing.
INSTRUCTIONS;
    }

    /**
     * The user driving the conversation. Enables automatic conversation
     * persistence while the BYOK provider is resolved separately.
     */
    public function conversationParticipant(): ?object
    {
        return $this->user;
    }

    /**
     * @see conversationParticipant()
     */
    public function hasConversationParticipant(): bool
    {
        return $this->user !== null;
    }

    /**
     * Send a chat message and get a text response.
     */
    public function chat(User $user, string $message, ?string $conversationId = null): string
    {
        $this->forUser($user);
        $this->ensureWithinAiCaps($user);
        $this->injectProviderKey();

        if ($conversationId !== null) {
            $this->continue($conversationId, $user);
        }

        $response = $this->prompt($message);

        $this->logUsage($user, $response);

        return (string) $response;
    }

    /**
     * Stream a chat message back to the caller token-by-token.
     */
    public function streamChat(User $user, string $message, ?string $conversationId = null): StreamableAgentResponse
    {
        $this->forUser($user);
        $this->ensureWithinAiCaps($user);
        $this->injectProviderKey();

        if ($conversationId !== null) {
            $this->continue($conversationId, $user);
        }

        return $this->stream($message);
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
