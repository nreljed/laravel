<?php

namespace App\Console\Commands;

use App\Blog\Models\Post;
use Illuminate\Console\Command;

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
