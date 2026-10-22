<?php

namespace Nreljed\MarketingModule\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Nreljed\MarketingModule\Models\SocialPost;
use Nreljed\MarketingModule\Services\PostPublisher;

/**
 * The scheduler's entry point into the publishing pipeline.
 *
 * Runs every minute and does nothing when the module's tables are not there
 * yet, so a fresh install can boot before `migrate` has run.
 */
class PublishDuePosts extends Command
{
    protected $signature = 'marketing:publish-due
        {--dry-run : Report the posts that are due without publishing them}';

    protected $description = 'Publish scheduled marketing posts whose time has come.';

    public function handle(PostPublisher $publisher): int
    {
        if (! Schema::hasTable('social_posts') || ! Schema::hasTable('social_post_targets')) {
            $this->warn('Marketing tables are not migrated yet; nothing to publish.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info(SocialPost::query()->due(now())->count().' post(s) are due.');

            return self::SUCCESS;
        }

        $summary = $publisher->publishDue();

        $this->info(sprintf(
            'Processed %d post(s): %d delivered, %d failed, %d retrying.',
            $summary['posts'],
            $summary['published'],
            $summary['failed'],
            $summary['retrying'],
        ));

        return self::SUCCESS;
    }
}
