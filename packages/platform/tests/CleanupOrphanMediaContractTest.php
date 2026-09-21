<?php

namespace Nreljed\Platform\Tests;

use Nreljed\Platform\Console\Commands\CleanupOrphanMedia;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Structural contract test for the kernel-owned CleanupOrphanMedia command.
 * Blog module folders are contributed via the feature registry
 * (getOrphanMediaTargets()), but the sweep mechanics — recursive month-folder
 * scanning and empty-directory pruning — stay kernel responsibilities.
 */
final class CleanupOrphanMediaContractTest extends TestCase
{
    public function test_cleanup_orphan_media_recurses_month_folders(): void
    {
        $source = (string) file_get_contents((new ReflectionClass(CleanupOrphanMedia::class))->getFileName());

        $this->assertStringContainsString('allFiles', $source, 'CleanupOrphanMedia must recurse YYYY/m subfolders instead of a top-level file scan.');
        $this->assertStringContainsString('pruneEmptyDirectories', $source, 'CleanupOrphanMedia must prune swept-empty subdirectories.');
        $this->assertStringContainsString('getAllOrphanMediaTargets', $source, 'CleanupOrphanMedia must merge feature-declared targets.');
    }
}