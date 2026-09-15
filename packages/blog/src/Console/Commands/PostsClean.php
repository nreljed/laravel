<?php

namespace Nreljed\Blog\Console\Commands;

use Illuminate\Console\Command;
use Nreljed\Blog\Models\Post;

class PostsClean extends Command
{
    protected $signature = 'posts:clean {--group=blog : Demo marker group key stored on posts as metadata._demo}';

    protected $description = 'Remove demo blog posts previously seeded (metadata._demo = group)';

    public function handle(): int
    {
        $group = (string) $this->option('group');

        $deleted = Post::where('metadata->_demo', $group)->delete();

        $this->info("Removed {$deleted} demo post(s) marked with metadata._demo = '{$group}'.");
        $this->line('Demo categories, tags and media are left intact; reseed with posts:seed or use the database maintenance reset.');

        return Command::SUCCESS;
    }
}
