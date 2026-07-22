<?php

namespace App\Ai\Agents;

use App\Ai\Agents\Traits\ResolvesUserProvider;
use App\Ai\Concerns\LogsAiUsage;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

class SeoAgent implements Agent, HasStructuredOutput
{
    use LogsAiUsage, Promptable, ResolvesUserProvider;

    /**
     * System instructions for SEO analysis.
     */
    public function instructions(): string
    {
        return <<<'INSTRUCTIONS'
You are an expert SEO specialist. Analyze webpage content and generate comprehensive SEO metadata.

For any page, you should produce:
- A compelling title tag (50-60 characters)
- A meta description (150-160 characters)
- Relevant keywords
- A canonical URL path
- JSON-LD structured data (Article or WebPage schema)
- An SEO score (1-100) with specific improvement recommendations

Always optimize for both search engines and human readers. Follow Google's latest guidelines.
INSTRUCTIONS;
    }

    /**
     * JSON schema for structured output.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()->required(),
            'meta_description' => $schema->string()->required(),
            'keywords' => $schema->array()->items($schema->string())->required(),
            'canonical_path' => $schema->string()->required(),
            'json_ld' => $schema->object()->required(),
            'seo_score' => $schema->integer()->min(1)->max(100)->required(),
            'recommendations' => $schema->array()->items($schema->string())->required(),
        ];
    }

    /**
     * Generate SEO metadata for a page.
     *
     * @return array{title: string, meta_description: string, keywords: array, canonical_path: string, json_ld: array, seo_score: int, recommendations: array}
     */
    public function generateSeo(User $user, string $title, string $content, ?string $url = null): array
    {
        $this->forUser($user);

        $prompt = "Generate comprehensive SEO metadata for this page:\n\n";
        $prompt .= "Title: {$title}\n";
        $prompt .= "Content: {$content}\n";
        if ($url) {
            $prompt .= "URL: {$url}\n";
        }

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
