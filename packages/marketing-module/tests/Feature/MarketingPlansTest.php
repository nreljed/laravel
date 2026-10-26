<?php

namespace Nreljed\MarketingModule\Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Nreljed\MarketingModule\Agents\MarketingStrategistAgent;
use Nreljed\MarketingModule\Models\MarketingPlan;
use Nreljed\MarketingModule\Services\MarketingAnalytics;
use Nreljed\MarketingModule\Services\MarketingPlanService;
use Nreljed\MarketingModule\Tests\Concerns\BootsDatabase;
use Nreljed\Platform\Models\User;
use PHPUnit\Framework\TestCase;

final class MarketingPlansTest extends TestCase
{
    use BootsDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootDatabase();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-23 12:00:00'));
        DB::table('users')->insert(['id' => 1, 'name' => 'Author', 'email' => 'author@example.test']);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_a_plan_can_be_approved(): void
    {
        $plan = MarketingPlan::create([
            'name' => 'Spring launch',
            'objective' => 'awareness',
            'status' => 'draft',
        ]);

        $user = (new User)->forceFill(['id' => 1]);

        $plan->approve($user);

        $this->assertTrue($plan->fresh()->isApproved());
        $this->assertSame(1, $plan->fresh()->approved_by);
    }

    public function test_the_strategist_service_creates_a_reviewable_draft_plan(): void
    {
        $agent = new class(new MarketingAnalytics) extends MarketingStrategistAgent
        {
            public function draftPlan(User $user, int $days = 30, ?string $goal = null): array
            {
                return [
                    'name' => 'AI spring plan',
                    'objective' => 'engagement',
                    'audience' => 'Existing audience',
                    'cadence' => '3 posts per week',
                    'channel_mix' => ['instagram', 'linkedin'],
                    'content_pillars' => ['education', 'proof'],
                    'kpi_targets' => ['engagement_rate: 5%'],
                    'constraints' => 'No paid spend',
                    'rationale' => 'Based on the current period.',
                    'next_steps' => ['Create a brief', 'Schedule a test'],
                ];
            }
        };

        $user = (new User)->forceFill(['id' => 1]);

        $plan = (new MarketingPlanService)->generate($user, 30, 'grow engagement', $agent);

        $this->assertSame('AI spring plan', $plan->name);
        $this->assertSame('draft', $plan->status->value);
        $this->assertSame('ai', $plan->generated_by);
        $this->assertSame(1, MarketingPlan::query()->count());
    }
}
