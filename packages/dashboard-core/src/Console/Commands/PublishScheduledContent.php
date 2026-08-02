<?php

namespace Nreljed\DashboardCore\Console\Commands;

use Illuminate\Console\Command;
use Nreljed\DashboardCore\Blog\Models\Post;

class PublishScheduledContent extends Command
{
    protected $signature = 'schedule:publish';

    protected $description = 'Publish scheduled posts with future status';

    public function handle(): int
    {
        $published = 0;

        $published += Post::where('status', 'future')
            ->where('published_at', '<=', now())
            ->update(['status' => 'published']);

        $this->info("Published {$published} items.");

        return Command::SUCCESS;
    }
}
