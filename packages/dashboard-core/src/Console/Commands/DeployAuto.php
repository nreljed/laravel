<?php

namespace Nreljed\DashboardCore\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Nreljed\DashboardCore\Deploy\Services\DeployService;

#[Signature('deploy:auto')]
#[Description('Check GitHub for a newer release and self-deploy from the server (CI fallback)')]
class DeployAuto extends Command
{
    public function handle(DeployService $deployService): int
    {
        $result = $deployService->check();

        return match ($result['status']) {
            'current' => $this->report('info', "Already up to date ({$result['tag']})."),
            'deployed' => $this->reportDeployed($result),
            'locked' => $this->report('info', 'Deploy already in progress; skipping.'),
            'no-release' => $this->report('warn', 'No release to deploy: '.$result['message']),
            default => $this->reportFailure($result),
        };
    }

    /**
     * @param  array{status: string, message: string, tag: string|null, output: array<int, string>, core: array<string, mixed>, status_code: int}  $result
     */
    private function reportDeployed(array $result): int
    {
        $this->info('Deployed '.$result['tag'].'.');

        foreach ($result['output'] as $line) {
            $this->line('  '.$line);
        }

        return 0;
    }

    /**
     * @param  array{status: string, message: string, tag: string|null, output: array<int, string>, core: array<string, mixed>, status_code: int}  $result
     */
    private function reportFailure(array $result): int
    {
        $this->error('Deploy failed: '.$result['message']);

        foreach ($result['output'] as $line) {
            $this->line('  '.$line);
        }

        return 1;
    }

    private function report(string $style, string $message): int
    {
        $this->{$style}($message);

        return 0;
    }
}
