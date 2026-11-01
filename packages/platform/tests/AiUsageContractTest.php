<?php

namespace Nreljed\Platform\Tests;

use Laravel\Ai\Responses\Data\Usage;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Guard against drift between the platform's AI usage logging and the
 * installed `laravel/ai` SDK.
 *
 * The SDK's Usage value object was renamed in v1.0: `promptTokens` and
 * `completionTokens` became `inputTokens` and `outputTokens`. Reading the old
 * property is not a hard failure (PHP returns null), so usage would silently
 * log as zero tokens — or throw a TypeError against the typed int parameters
 * on AiTaskRunner::logUsage(). This contract pins both sides.
 */
final class AiUsageContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = dirname(__DIR__);
    }

    public function test_sdk_usage_exposes_the_renamed_token_properties(): void
    {
        $usage = new Usage(inputTokens: 5, outputTokens: 3);

        $this->assertSame(5, $usage->inputTokens);
        $this->assertSame(3, $usage->outputTokens);
        $this->assertSame(8, $usage->totalTokens());
        $this->assertFalse(property_exists($usage, 'promptTokens'));
        $this->assertFalse(property_exists($usage, 'completionTokens'));
    }

    public function test_platform_never_reads_the_removed_usage_properties(): void
    {
        $violations = [];

        foreach ($this->phpFiles() as $file) {
            $content = (string) file_get_contents($file);

            if (preg_match('/->(promptTokens|completionTokens)\b/', $content)) {
                $violations[] = str_replace('\\', '/', substr($file, strlen($this->root.'/')));
            }
        }

        $this->assertSame(
            [],
            $violations,
            'Files still reading the pre-1.0 laravel/ai usage properties (use inputTokens/outputTokens):'.PHP_EOL.implode(PHP_EOL, $violations),
        );
    }

    /**
     * @return list<string>
     */
    private function phpFiles(): array
    {
        $files = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->root.'/src', RecursiveDirectoryIterator::SKIP_DOTS),
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}
