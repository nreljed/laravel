<?php

namespace Nreljed\Blog\Console\Commands;

use Illuminate\Console\Command;
use Nreljed\Blog\Models\Post;

class PublishScheduledContent extends Command
{
    protected $signature = 'schedule:publish';

    protected $description = 'Publish scheduled posts with future status';

    public function handle(): int
    {
        $published = 0;

        Post::where('status', 'future')
            ->where('published_at', '<=', now())
            ->each(function (Post $post) use (&$published) {
                $post->update(['status' => 'published']);
                $published++;
            });

        $this->info("Published {$published} items.");

        return Command::SUCCESS;
    }
}
