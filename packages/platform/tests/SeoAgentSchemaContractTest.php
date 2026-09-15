<?php

namespace Nreljed\Platform\Tests;

use Nreljed\Platform\Ai\Agents\SeoAgent;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Structural contract test for the kernel-owned SeoAgent. The schema the BYOK
 * SEO agent emits is consumed by the blog package's SeoGenerationController /
 * GenerateSeoForPost, so the schema surface is pinned here rather than in a
 * feature-specific suite.
 */
final class SeoAgentSchemaContractTest extends TestCase
{
    public function test_seo_agent_schema_declares_term_suggestions(): void
    {
        $source = (string) file_get_contents((new ReflectionClass(SeoAgent::class))->getFileName());

        $this->assertStringContainsString(
            "'suggested_category' => \$schema->string()->nullable()",
            $source,
            'SeoAgent schema must declare an optional suggested_category.'
        );
        $this->assertStringContainsString(
            "'suggested_tags' => \$schema->array()->items(\$schema->string())->max(5)",
            $source,
            'SeoAgent schema must declare up to five suggested_tags.'
        );
    }
}
