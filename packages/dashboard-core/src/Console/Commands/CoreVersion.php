<?php

namespace Nreljed\DashboardCore\Console\Commands;

use Illuminate\Console\Command;
use Nreljed\DashboardCore\Services\CoreVersionService;

class CoreVersion extends Command
{
    protected $signature = 'core:version
        {--format=table : Output format: table or json}';

    protected $description = 'Show the installed dashboard-core package name, version, and commit';

    public function handle(CoreVersionService $core): int
    {
        $info = $core->info();

        if ($this->option('format') === 'json') {
            $this->line(json_encode($info, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return Command::SUCCESS;
        }

        $this->table(
            ['Key', 'Value'],
            [
                ['name', $info['name']],
                ['version', $info['version'] ?? '(unrecorded)'],
                ['sha', $info['sha'] ?? '(unrecorded)'],
                ['short', $core->shortSha() ?? '(unrecorded)'],
                ['commit_url', $core->commitUrl() ?? '(unrecorded)'],
            ]
        );

        return Command::SUCCESS;
    }
}
