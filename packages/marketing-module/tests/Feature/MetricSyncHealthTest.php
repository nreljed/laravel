<?php

namespace Nreljed\MarketingModule\Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Nreljed\MarketingModule\Enums\DeliveryStatus;
use Nreljed\MarketingModule\Enums\MetricSyncStatus;
use Nreljed\MarketingModule\Enums\PostStatus;
use Nreljed\MarketingModule\Enums\SocialAccountStatus;
use Nreljed\MarketingModule\Models\SocialAccount;
use Nreljed\MarketingModule\Models\SocialPost;
use Nreljed\MarketingModule\Models\SocialPostTarget;
use Nreljed\MarketingModule\Services\MetricSyncBudget;
use Nreljed\MarketingModule\Services\MetricSyncHealth;
use Nreljed\MarketingModule\Tests\Concerns\BootsDatabase;
use PHPUnit\Framework\TestCase;

final class MetricSyncHealthTest extends TestCase
{
    use BootsDatabase;

    private CarbonImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootDatabase();
        $this->now = CarbonImmutable::parse('2026-10-25 12:00:00');

        DB::table('users')->insert(['id' => 1, 'name' => 'Author', 'email' => 'author@example.test']);
    }

    public function test_it_groups_deliveries_by_platform_and_reports_the_budget(): void
    {
        $this->container['config']->set('marketing.metrics.platforms', [
            'fake' => ['daily_request_budget' => 2, 'requests_per_minute' => 12, 'max_per_run' => 40],
        ]);

        $fake = $this->account('fake');

        $this->target($fake, [
            'metrics_status' => MetricSyncStatus::Ok,
            'metrics_synced_at' => $this->now->subMinutes(10),
        ]);
        $this->target($fake, [
            'metrics_status' => MetricSyncStatus::Error,
            'metrics_attempts' => 1,
            'metrics_error' => 'Attempt 1 of 5.',
        ]);
        $this->target($fake, [
            'metrics_status' => MetricSyncStatus::DeadLetter,
            'metrics_attempts' => 3,
            'metrics_error' => 'Permanent failure. Dead letter after 3 attempt(s).',
            'metrics_attempted_at' => $this->now->subHour(),
        ]);

        $linkedin = $this->account('linkedin');
        $this->target($linkedin, ['metrics_status' => MetricSyncStatus::Pending]);

        // One call already spent from the fake platform's two-call budget.
        (new MetricSyncBudget)->consume('fake', $this->now);

        $health = (new MetricSyncHealth(new MetricSyncBudget))->overview(now: $this->now);

        $this->assertSame(4, $health['totals']['total']);
        $this->assertSame(1, $health['totals']['ok']);
        $this->assertSame(1, $health['totals']['pending']);
        $this->assertSame(1, $health['totals']['error']);
        $this->assertSame(1, $health['totals']['dead_letter']);

        $platforms = collect($health['platforms'])->keyBy('platform');

        $this->assertSame(3, $platforms['fake']['total']);
        $this->assertSame(1, $platforms['fake']['ok']);
        $this->assertSame(1, $platforms['fake']['error']);
        $this->assertSame(1, $platforms['fake']['dead_letter']);
        $this->assertSame(2, $platforms['fake']['daily_request_budget']);
        $this->assertSame(1, $platforms['fake']['budget_used']);
        $this->assertSame(1, $platforms['fake']['budget_remaining']);
        $this->assertFalse($platforms['fake']['budget_exhausted']);
        $this->assertSame(12, $platforms['fake']['requests_per_minute']);
        $this->assertSame(40, $platforms['fake']['max_per_run']);
        $this->assertNotNull($platforms['fake']['last_synced_at']);

        $this->assertSame(1, $platforms['linkedin']['total']);
        $this->assertSame(0, $platforms['linkedin']['daily_request_budget']);
        $this->assertNull($platforms['linkedin']['budget_remaining']);
        $this->assertFalse($platforms['linkedin']['budget_exhausted']);

        $this->assertCount(1, $health['dead_letters']);
        $this->assertSame(3, $health['dead_letters'][0]['attempts']);
        $this->assertStringContainsString('Permanent failure', (string) $health['dead_letters'][0]['error']);
        $this->assertSame('fake', $health['dead_letters'][0]['platform']);
    }

    public function test_budget_exhaustion_is_visible(): void
    {
        $this->container['config']->set('marketing.metrics.daily_request_budget', 1);

        $account = $this->account('fake');
        $this->target($account, ['metrics_status' => MetricSyncStatus::Pending]);

        $budget = new MetricSyncBudget;
        $budget->consume('fake', $this->now);

        $health = (new MetricSyncHealth($budget))->overview(now: $this->now);
        $platform = collect($health['platforms'])->firstWhere('platform', 'fake');

        $this->assertSame(1, $platform['budget_used']);
        $this->assertSame(0, $platform['budget_remaining']);
        $this->assertTrue($platform['budget_exhausted']);
    }

    public function test_a_configured_platform_without_deliveries_still_appears(): void
    {
        $this->container['config']->set('marketing.metrics.platforms', [
            'linkedin' => ['daily_request_budget' => 10],
        ]);

        $health = (new MetricSyncHealth(new MetricSyncBudget))->overview(now: $this->now);

        $platform = collect($health['platforms'])->firstWhere('platform', 'linkedin');

        $this->assertNotNull($platform);
        $this->assertSame(0, $platform['total']);
        $this->assertSame(10, $platform['daily_request_budget']);
        $this->assertSame(10, $platform['budget_remaining']);
    }

    public function test_dead_letters_can_be_filtered_by_platform(): void
    {
        $fake = $this->account('fake');
        $linkedin = $this->account('linkedin');

        foreach ([$fake, $linkedin] as $account) {
            $this->target($account, [
                'metrics_status' => MetricSyncStatus::DeadLetter,
                'metrics_attempts' => 2,
                'metrics_error' => 'Permanent failure.',
                'metrics_attempted_at' => $this->now,
            ]);
        }

        $health = new MetricSyncHealth(new MetricSyncBudget);

        $this->assertCount(2, $health->deadLetters());
        $this->assertCount(1, $health->deadLetters(platform: 'linkedin'));
        $this->assertSame('linkedin', $health->deadLetters(platform: 'linkedin')[0]['platform']);
    }

    /**
     * @param  array<string, mixed>  $metrics
     */
    private function target(SocialAccount $account, array $metrics = []): SocialPostTarget
    {
        $post = SocialPost::create([
            'user_id' => 1,
            'body' => 'Published post',
            'status' => PostStatus::Published,
            'scheduled_at' => $this->now->subHour(),
            'published_at' => $this->now->subHour(),
            'requires_approval' => false,
            'approved_at' => $this->now->subHour(),
        ]);

        return SocialPostTarget::create(array_merge([
            'social_post_id' => $post->id,
            'social_account_id' => $account->id,
            'status' => DeliveryStatus::Published,
            'attempts' => 1,
            'external_post_id' => 'external-'.$post->id,
            'published_at' => $this->now->subHour(),
            'metrics_status' => MetricSyncStatus::Pending,
        ], $metrics));
    }

    private function account(string $platform): SocialAccount
    {
        static $sequence = 0;

        $sequence++;

        return SocialAccount::create([
            'user_id' => 1,
            'platform' => $platform,
            'account_id' => 'account-'.$sequence,
            'account_name' => 'Test account '.$sequence,
            'username' => 'test'.$sequence,
            'scopes' => [],
            'status' => SocialAccountStatus::Active,
            'metadata' => [],
        ]);
    }
}
