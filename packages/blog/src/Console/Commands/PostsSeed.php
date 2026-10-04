<?php

namespace Nreljed\Blog\Console\Commands;

use Illuminate\Console\Command;

class PostsSeed extends Command
{
    protected $signature = 'posts:seed {--group=blog : Demo seeder group key from database_maintenance.seeders}';

    protected $description = 'Seed demo blog posts using the configured demo seeder';

    public function handle(): int
    {
        $class = (string) config("database_maintenance.seeders.{$this->option('group')}.class");

        if ($class === '') {
            $this->error("No demo seeder configured under database_maintenance.seeders.{$this->option('group')}.class.");

            return Command::FAILURE;
        }

        $exit = $this->callSilently('db:seed', [
            '--class' => $class,
            '--force' => true,
        ]);

        if ($exit !== 0) {
            $this->error("Demo seeder '{$class}' failed.");

            return Command::FAILURE;
        }

        $this->info("Seeded demo blog content via {$class}.");

        return Command::SUCCESS;
    }
}