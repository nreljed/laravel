<?php

namespace App\Console\Commands;

use App\Modules\Posts\Models\Post;
use App\Modules\Properties\Models\Property;
use App\Modules\Shop\Models\Product;
use Illuminate\Console\Command;

class PublishScheduledContent extends Command
{
    protected $signature = 'schedule:publish';

    protected $description = 'Publish scheduled content (products, posts) with future status';

    public function handle(): int
    {
        $published = 0;

        $published += Product::where('status', 'future')
            ->where('publish_date', '<=', now())
            ->update(['status' => 'published', 'publish_date' => null]);

        $published += Post::where('status', 'future')
            ->where('published_at', '<=', now())
            ->update(['status' => 'published']);

        $published += Property::scheduled()->update(['status' => 'published']);

        $this->info("Published {$published} items.");

        return Command::SUCCESS;
    }
}
