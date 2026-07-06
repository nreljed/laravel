<?php

namespace App\Console\Commands;

use App\Modules\Properties\Models\Property;
use Illuminate\Console\Command;

class PublishScheduledProperties extends Command
{
    protected $signature = 'properties:publish-scheduled';

    protected $description = 'Publish properties that have a scheduled publish date in the past';

    public function handle(): int
    {
        $count = Property::scheduled()->update([
            'status' => 'published',
        ]);

        $this->info("Published {$count} scheduled properties.");

        return Command::SUCCESS;
    }
}
