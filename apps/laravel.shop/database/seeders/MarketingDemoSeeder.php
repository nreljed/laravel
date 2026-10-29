<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Nreljed\MarketingModule\Enums\CampaignObjective;
use Nreljed\MarketingModule\Enums\ContactStatus;
use Nreljed\MarketingModule\Enums\MarketingBriefStatus;
use Nreljed\MarketingModule\Enums\MarketingPlanStatus;
use Nreljed\MarketingModule\Enums\PostStatus;
use Nreljed\MarketingModule\Enums\SocialAccountStatus;
use Nreljed\MarketingModule\Enums\WorkflowAction;
use Nreljed\MarketingModule\Enums\WorkflowTrigger;
use Nreljed\MarketingModule\Models\Campaign;
use Nreljed\MarketingModule\Models\Contact;
use Nreljed\MarketingModule\Models\MarketingBrief;
use Nreljed\MarketingModule\Models\MarketingLink;
use Nreljed\MarketingModule\Models\MarketingLinkClick;
use Nreljed\MarketingModule\Models\MarketingPlan;
use Nreljed\MarketingModule\Models\Segment;
use Nreljed\MarketingModule\Models\SocialAccount;
use Nreljed\MarketingModule\Models\SocialPost;
use Nreljed\MarketingModule\Models\Tag;
use Nreljed\MarketingModule\Models\Workflow;
use Nreljed\Platform\Models\User;

class MarketingDemoSeeder extends Seeder
{
    public function run(): void
    {
        // Idempotent: skip if marketing demo data already exists.
        if (Campaign::count() > 0) {
            $this->command?->info('Marketing demo data already present; skipping.');

            return;
        }

        $admin = User::where('email', 'nstudio.webdesign@gmail.com')->first()
            ?? User::whereHas('roles', fn ($q) => $q->where('slug', 'admin'))->first();

        if (! $admin) {
            $this->command?->warn('MarketingDemoSeeder: no admin user found; skipping.');

            return;
        }

        $tags = Tag::factory()->count(5)->create();

        $contacts = Contact::factory()->count(8)->create();
        Contact::factory()->count(4)->create(['status' => ContactStatus::Active]);

        $segments = Segment::factory()->count(4)->create();

        $plans = collect([
            MarketingPlan::create([
                'name' => 'Q2 Growth Plan',
                'objective' => CampaignObjective::Sales,
                'status' => MarketingPlanStatus::Approved,
                'audience' => 'Existing customers + lookalike audiences',
                'cadence' => 'Weekly',
                'channel_mix' => ['social', 'email'],
                'content_pillars' => ['Product launches', 'Tips & guides'],
                'kpi_targets' => ['revenue' => 20000, 'conversions' => 500],
                'constraints' => null,
                'starts_at' => now()->subWeek(),
                'ends_at' => now()->addMonths(2),
                'approved_by' => $admin->id,
                'approved_at' => now()->subDays(3),
                'generated_by' => 'manual',
                'metadata' => [],
            ]),
            MarketingPlan::create([
                'name' => 'Brand Awareness Plan',
                'objective' => CampaignObjective::Awareness,
                'status' => MarketingPlanStatus::Draft,
                'audience' => 'Broad consumer audience',
                'cadence' => 'Bi-weekly',
                'channel_mix' => ['social'],
                'content_pillars' => ['Brand stories', 'Behind the scenes'],
                'kpi_targets' => ['reach' => 100000, 'engagement' => 5000],
                'constraints' => null,
                'starts_at' => now(),
                'ends_at' => now()->addMonth(),
                'approved_by' => null,
                'approved_at' => null,
                'generated_by' => 'manual',
                'metadata' => [],
            ]),
        ]);

        Campaign::factory()->count(2)->create([
            'marketing_plan_id' => $plans->first()->id,
        ]);
        Campaign::factory()->active()->create([
            'marketing_plan_id' => $plans->last()->id,
            'name' => 'Spring Launch Campaign',
        ]);

        MarketingBrief::create([
            'marketing_plan_id' => $plans->first()->id,
            'title' => 'Spring product launch brief',
            'objective' => CampaignObjective::Sales,
            'audience' => 'Existing customers',
            'key_message' => 'New products are here — built for you.',
            'channels' => ['email', 'social'],
            'status' => MarketingBriefStatus::Ready,
            'due_at' => now()->addWeek(),
            'created_by' => $admin->id,
            'metadata' => [],
        ]);
        MarketingBrief::create([
            'marketing_plan_id' => $plans->last()->id,
            'title' => 'Brand awareness brief',
            'objective' => CampaignObjective::Awareness,
            'audience' => 'Broad audience',
            'key_message' => 'We build tools that make life easier.',
            'channels' => ['social'],
            'status' => MarketingBriefStatus::Draft,
            'due_at' => now()->addWeeks(2),
            'created_by' => $admin->id,
            'metadata' => [],
        ]);

        Workflow::factory()->count(2)->create([
            'is_active' => true,
        ]);
        Workflow::factory()->create([
            'name' => 'Inactive welcome flow',
            'is_active' => false,
        ]);

        // Order-placed workflow (shop integration): tag buyers as customers.
        $buyerTag = Tag::firstOrCreate(
            ['slug' => 'buyer'],
            ['name' => 'Buyer', 'color' => fake()->hexColor()],
        );
        Workflow::create([
            'name' => 'Tag new buyers',
            'description' => 'Tags a contact as a buyer when their order is processed.',
            'trigger' => WorkflowTrigger::OrderPlaced,
            'action' => WorkflowAction::AddTag,
            'tag_id' => $buyerTag->id,
            'is_active' => true,
            'run_count' => 0,
            'metadata' => [],
        ]);

        $connected = SocialAccount::factory()->connected()->create([
            'user_id' => $admin->id,
            'platform' => 'facebook',
            'account_name' => 'Shop App',
        ]);
        SocialAccount::factory()->expired()->create([
            'user_id' => $admin->id,
            'platform' => 'instagram',
        ]);
        SocialAccount::factory()->create([
            'user_id' => $admin->id,
            'platform' => 'linkedin',
            'status' => SocialAccountStatus::Error,
        ]);

        SocialPost::factory()->count(3)->create([
            'user_id' => $admin->id,
        ]);
        SocialPost::factory()->scheduled()->create([
            'user_id' => $admin->id,
            'body' => 'Spring sale is live — up to 30% off selected items. Shop now!',
        ]);
        SocialPost::factory()->create([
            'user_id' => $admin->id,
            'body' => 'New blog post: 5 tips for choosing the right smartphone.',
            'status' => PostStatus::Published,
            'published_at' => now()->subDays(2),
        ]);

        $post = SocialPost::first();
        $link = MarketingLink::factory()->create([
            'social_post_id' => $post?->id,
            'destination_url' => 'https://shop.laravel.tunispromo.tn/store/products',
            'short_code' => Str::random(8),
            'is_active' => true,
        ]);
        MarketingLinkClick::factory()->count(12)->create([
            'marketing_link_id' => $link->id,
        ]);

        $this->command?->info('Marketing demo data seeded.');
    }
}
