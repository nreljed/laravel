<?php

namespace Nreljed\MarketingModule\Tests\Unit;

use Carbon\CarbonImmutable;
use Nreljed\MarketingModule\Services\MetricSyncBudget;
use Nreljed\MarketingModule\Tests\Concerns\BootsDatabase;
use PHPUnit\Framework\TestCase;

final class MetricSyncBudgetTest extends TestCase
{
    use BootsDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootDatabase();
    }

    public function test_it_uses_global_limits_and_per_platform_overrides(): void
    {
        $this->container['config']->set('marketing.metrics', [
            'requests_per_minute' => 30,
            'max_per_run' => 100,
            'daily_request_budget' => 0,
            'platforms' => [
                'linkedin' => [
                    'requests_per_minute' => 10,
                    'max_per_run' => 25,
                    'daily_request_budget' => 500,
                ],
            ],
        ]);

        $budget = new MetricSyncBudget;

        $this->assertSame(30, $budget->settings(null)['requests_per_minute']);
        $this->assertSame(100, $budget->settings(null)['max_per_run']);
        $this->assertSame(0, $budget->settings(null)['daily_request_budget']);

        $this->assertSame(10, $budget->settings('linkedin')['requests_per_minute']);
        $this->assertSame(25, $budget->settings('linkedin')['max_per_run']);
        $this->assertSame(500, $budget->settings('linkedin')['daily_request_budget']);
    }

    public function test_a_zero_budget_is_uncapped_rather_than_blocked(): void
    {
        $this->container['config']->set('marketing.metrics.daily_request_budget', 0);

        $budget = new MetricSyncBudget;

        $this->assertNull($budget->remaining('fake'));
        $this->assertTrue($budget->consume('fake'));
        $this->assertTrue($budget->consume('fake'));
        $this->assertSame(2, $budget->used('fake'));
        $this->assertNull($budget->remaining('fake'));
    }

    public function test_consume_stops_at_the_daily_budget_without_overshooting(): void
    {
        $this->container['config']->set('marketing.metrics.daily_request_budget', 2);

        $budget = new MetricSyncBudget;
        $now = CarbonImmutable::parse('2026-10-25 09:00:00');

        $this->assertTrue($budget->consume('fake', $now));
        $this->assertTrue($budget->consume('fake', $now));
        $this->assertFalse($budget->consume('fake', $now));

        $this->assertSame(2, $budget->used('fake', $now));
        $this->assertSame(0, $budget->remaining('fake', $now));
    }

    public function test_usage_is_scoped_per_platform_and_per_utc_day(): void
    {
        $this->container['config']->set('marketing.metrics.daily_request_budget', 5);

        $budget = new MetricSyncBudget;
        $monday = CarbonImmutable::parse('2026-10-25 09:00:00');
        $tuesday = $monday->addDay();

        $this->assertTrue($budget->consume('fake', $monday));
        $this->assertTrue($budget->consume('fake', $monday));

        $this->assertSame(2, $budget->used('fake', $monday));
        $this->assertSame(0, $budget->used('other', $monday));
        $this->assertSame(0, $budget->used('fake', $tuesday));
        $this->assertSame(5, $budget->remaining('fake', $tuesday));
    }

    public function test_reset_forgets_only_the_given_platform_and_day(): void
    {
        $this->container['config']->set('marketing.metrics.daily_request_budget', 5);

        $budget = new MetricSyncBudget;
        $now = CarbonImmutable::parse('2026-10-25 09:00:00');

        $budget->consume('fake', $now);
        $budget->consume('other', $now);

        $budget->reset('fake', $now);

        $this->assertSame(0, $budget->used('fake', $now));
        $this->assertSame(1, $budget->used('other', $now));
    }

    public function test_request_delay_is_derived_from_the_minute_limit(): void
    {
        $this->container['config']->set('marketing.metrics.requests_per_minute', 30);
        $this->container['config']->set('marketing.metrics.platforms', [
            'slow' => ['requests_per_minute' => 6],
        ]);

        $budget = new MetricSyncBudget;

        $this->assertSame(2_000_000, $budget->requestDelay('fake'));
        $this->assertSame(10_000_000, $budget->requestDelay('slow'));
    }
}
