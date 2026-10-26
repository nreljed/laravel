<?php

namespace Nreljed\MarketingModule\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Nreljed\MarketingModule\Services\MetricSyncHealth;
use Nreljed\MarketingModule\Services\SocialMetricSync;

/**
 * Operator tool for the dead-letter queue.
 *
 * A dead letter is a delivery the sync loop has stopped retrying: either the
 * provider returned a permanent error, or the transient retry budget ran out.
 * Listing it is safe; `--replay` puts it back in front of the next sync run.
 */
class ReplayMarketingDeadLetters extends Command
{
    protected $signature = 'marketing:metrics-dead-letters
        {--platform= : Restrict to one platform key}
        {--limit=25 : Maximum dead letters to list or replay}
        {--replay : Re-queue the matched dead letters for retry}';

    protected $description = 'List dead-lettered metric syncs, or re-queue them for retry.';

    public function handle(MetricSyncHealth $health, SocialMetricSync $sync): int
    {
        if (! Schema::hasTable('social_post_targets')) {
            $this->warn('The social_post_targets table is not migrated yet.');

            return self::SUCCESS;
        }

        $platform = is_string($this->option('platform')) && $this->option('platform') !== ''
            ? (string) $this->option('platform')
            : null;
        $limit = is_numeric($this->option('limit')) ? max(1, (int) $this->option('limit')) : 25;

        if ((bool) $this->option('replay')) {
            $replayed = $sync->replayDeadLetters($platform, $limit);
            $this->info("Re-queued {$replayed} dead letter(s) for retry.");

            return self::SUCCESS;
        }

        $deadLetters = $health->deadLetters($limit, $platform);

        if ($deadLetters === []) {
            $this->info('No dead letters.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Platform', 'Post', 'Attempts', 'Last attempt', 'Error'],
            array_map(static fn (array $row): array => [
                $row['id'],
                $row['platform_label'],
                $row['body'],
                $row['attempts'],
                $row['attempted_at'] ?? '-',
                $row['error'] ?? '-',
            ], $deadLetters),
        );

        $this->line('Replay with --replay once the provider or credentials are fixed.');

        return self::SUCCESS;
    }
}
