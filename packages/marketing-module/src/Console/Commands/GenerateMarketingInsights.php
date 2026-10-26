<?php

namespace Nreljed\MarketingModule\Console\Commands;

use Illuminate\Console\Command;
use Nreljed\MarketingModule\Services\MarketingInsightService;

class GenerateMarketingInsights extends Command
{
    protected $signature = 'marketing:generate-insights {--days=30 : Period to analyze (7,30,90)}';

    protected $description = 'Generate rule-based marketing insights and recommendations.';

    public function handle(MarketingInsightService $insights): int
    {
        if (! (bool) config('marketing.enabled', true)) {
            $this->warn('Marketing feature disabled.');

            return self::SUCCESS;
        }

        $days = (int) $this->option('days');
        $days = in_array($days, [7, 30, 90], true) ? $days : 30;

        $created = $insights->refresh($days);

        $this->info('Generated '.count($created).' new insight(s).');

        return self::SUCCESS;
    }
}
