<?php

namespace Nreljed\DashboardCore\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Nreljed\DashboardCore\Ai\Agents\Traits\ResolvesUserProvider;
use Nreljed\DashboardCore\Ai\Concerns\LogsAiUsage;
use Nreljed\DashboardCore\Models\User;

class SeoAgent implements Agent, HasStructuredOutput
{
    use LogsAiUsage, Promptable, ResolvesUserProvider;

    /**
     * System instructions for SEO analysis.
     */
    public function instructions(): string
    {
        return <<<'INSTRUCTIONS'
You are an expert SEO specialist with deep knowledge of Google's latest guidelines, E-E-A-T principles, and SERP optimization.

When generating SEO metadata:
- Create multiple title variations with different angles (informational, curiosity-driven, number-based, question-based)
- Create multiple meta description variations (each 150-160 characters)
- Always optimize for both search engines and human readers
- Follow Google's title tag best practices (no keyword stuffing, natural language)
- Include the target keyword naturally in titles and descriptions
- Score realistically based on keyword optimization, content relevance, and SERP competitiveness

When a target keyword is provided, all suggestions MUST incorporate that keyword naturally.
INSTRUCTIONS;
    }

    /**
     * JSON schema for structured output.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'title_options' => $schema->array()->items($schema->string())->min(3)->max(5)->required(),
            'meta_description_options' => $schema->array()->items($schema->string())->min(3)->max(5)->required(),
            'primary_keyword' => $schema->string()->required(),
            'secondary_keywords' => $schema->array()->items($schema->string())->required(),
            'seo_score' => $schema->integer()->min(0)->max(100)->required(),
            'recommendations' => $schema->array()->items($schema->object([
                'field' => $schema->string()->enum(['title', 'meta_description', 'content', 'structure', 'keyword'])->required(),
                'severity' => $schema->string()->enum(['critical', 'warning', 'info'])->required(),
                'message' => $schema->string()->required(),
            ]))->required(),
        ];
    }

    /**
     * Generate SEO metadata options for a post.
     *
     * @return array{title_options: string[], meta_description_options: string[], primary_keyword: string, secondary_keywords: string[], seo_score: int, recommendations: array}
     */
    public function generateSeo(User $user, string $title, string $content, ?string $keyword = null): array
    {
        $this->forUser($user);

        $prompt = $this->buildPrompt($title, $content, $keyword);

        $this->injectProviderKey();

        $response = $this->prompt($prompt);

        $meta = $response instanceof StructuredAgentResponse ? $response->toArray() : [];

        $resolved = $this->getResolvedProvider();
        $this->logAiUsage(
            user: $user,
            provider: $resolved['driver'],
            model: $resolved['model'] ?? 'unknown',
            inputTokens: $response->usage->promptTokens,
            outputTokens: $response->usage->completionTokens,
        );

        return $meta;
    }

    /**
     * Build the SEO generation prompt.
     */
    protected function buildPrompt(string $title, string $content, ?string $keyword): string
    {
        $prompt = "Generate comprehensive SEO metadata for this blog post:\n\n";
        $prompt .= "Title: {$title}\n";
        $prompt .= "Content: {$content}\n";

        if ($keyword) {
            $prompt .= "\nTARGET KEYWORD: \"{$keyword}\"\n";
            $prompt .= "The keyword MUST appear naturally in at least 2 of the title options and 2 of the meta description options.\n";
            $prompt .= "Also suggest 3-5 secondary/LSI keywords related to \"{$keyword}\".\n";
        } else {
            $prompt .= "\nSuggest a primary keyword that best represents this content.\n";
            $prompt .= "Also suggest 3-5 secondary/LSI keywords.\n";
        }

        $prompt .= "\nGenerate 3-5 title variations. Each must be under 60 characters. Use different angles:\n";
        $prompt .= "- One straightforward/informational\n";
        $prompt .= "- One curiosity-driven\n";
        $prompt .= "- One number/list-based if applicable\n";
        $prompt .= "- One question-based if applicable\n";
        $prompt .= "- One with a power word or emotional trigger\n\n";

        $prompt .= "Generate 3-5 meta description variations. Each must be 150-160 characters. Focus on:\n";
        $prompt .= "- Clear value proposition\n";
        $prompt .= "- Call-to-action\n";
        $prompt .= "- Natural keyword placement\n\n";

        $prompt .= "Provide a realistic SEO score (0-100) based on:\n";
        $prompt .= "- Title optimization (keyword placement, length)\n";
        $prompt .= "- Description optimization (engagement potential, length)\n";
        $prompt .= "- Content depth and relevance\n";

        $prompt .= "\nProvide 3-5 specific, actionable recommendations for improving SEO.";

        return $prompt;
    }
}
