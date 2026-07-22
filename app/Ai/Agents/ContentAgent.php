<?php

namespace App\Ai\Agents;

use App\Ai\Agents\Traits\ResolvesUserProvider;
use App\Ai\Concerns\LogsAiUsage;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

class ContentAgent implements Agent, HasStructuredOutput
{
    use LogsAiUsage, Promptable, ResolvesUserProvider;

    /**
     * System instructions for content generation.
     */
    public function instructions(): string
    {
        return <<<'INSTRUCTIONS'
You are an expert content writer and editor for business applications.
You help users create, edit, and improve content including:
- Blog posts and articles
- Email templates
- Marketing copy
- Documentation
- Social media content

Follow brand voice guidelines when provided. Maintain consistent tone and style. Always ensure content is clear, engaging, and error-free.
INSTRUCTIONS;
    }

    /**
     * JSON schema for structured output.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()->required(),
            'content' => $schema->string()->required(),
            'summary' => $schema->string()->required(),
            'tags' => $schema->array()->items($schema->string())->required(),
            'tone' => $schema->string()->required(),
            'word_count' => $schema->integer()->required(),
        ];
    }

    /**
     * Generate content from a prompt.
     *
     * @return array{title: string, content: string, summary: string, tags: array, tone: string, word_count: int}
     */
    public function generate(User $user, string $prompt, ?string $context = null): array
    {
        $this->forUser($user);

        $fullPrompt = "Generate content based on this request:\n\n";
        $fullPrompt .= "Request: {$prompt}\n";
        if ($context) {
            $fullPrompt .= "Context: {$context}\n";
        }

        $response = $this->prompt($fullPrompt);

        $result = $response->json();
        $meta = $result ?? $response->toArray();

        $usage = method_exists($response, 'usage') ? $response->usage() : [];

        $resolved = $this->getResolvedProvider();
        $this->logAiUsage(
            user: $user,
            provider: $resolved['driver'],
            model: $resolved['model'] ?? 'unknown',
            inputTokens: $usage['input_tokens'] ?? 0,
            outputTokens: $usage['output_tokens'] ?? 0,
        );

        return $meta;
    }

    /**
     * Improve existing content based on instructions.
     *
     * @return array{title: string, content: string, summary: string, tags: array, tone: string, word_count: int}
     */
    public function improve(User $user, string $content, string $instructions): array
    {
        $this->forUser($user);

        $prompt = "Improve this content:\n\n";
        $prompt .= "Original content:\n{$content}\n\n";
        $prompt .= "Improvement instructions: {$instructions}\n";

        $response = $this->prompt($prompt);

        $result = $response->json();
        $meta = $result ?? $response->toArray();

        $usage = method_exists($response, 'usage') ? $response->usage() : [];

        $resolved = $this->getResolvedProvider();
        $this->logAiUsage(
            user: $user,
            provider: $resolved['driver'],
            model: $resolved['model'] ?? 'unknown',
            inputTokens: $usage['input_tokens'] ?? 0,
            outputTokens: $usage['output_tokens'] ?? 0,
        );

        return $meta;
    }
}
