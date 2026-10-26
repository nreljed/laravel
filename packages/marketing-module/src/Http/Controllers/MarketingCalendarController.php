<?php

namespace Nreljed\MarketingModule\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Nreljed\MarketingModule\Models\MarketingBrief;
use Nreljed\MarketingModule\Models\MarketingPlan;
use Nreljed\MarketingModule\Models\SocialPost;
use Nreljed\Platform\Http\Controllers\Controller;

class MarketingCalendarController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $month = (string) $request->query('month', CarbonImmutable::today()->format('Y-m'));
        try {
            $start = CarbonImmutable::parse($month.'-01')->startOfMonth();
        } catch (\Throwable) {
            $start = CarbonImmutable::today()->startOfMonth();
        }
        $end = $start->endOfMonth();

        $posts = SocialPost::query()
            ->with('campaign:id,name')
            ->where(function ($query) use ($start, $end): void {
                $query->whereBetween('scheduled_at', [$start, $end])
                    ->orWhereBetween('published_at', [$start, $end]);
            })
            ->orderBy('scheduled_at')
            ->get()
            ->map(fn (SocialPost $post): array => [
                'id' => $post->id,
                'body' => mb_substr((string) $post->body, 0, 80),
                'status' => $post->status?->value,
                'scheduled_at' => $post->scheduled_at?->toDateTimeString(),
                'published_at' => $post->published_at?->toDateTimeString(),
                'campaign' => $post->campaign?->name,
            ])
            ->all();

        $briefs = MarketingBrief::query()
            ->whereBetween('due_at', [$start, $end])
            ->orderBy('due_at')
            ->get()
            ->map(fn (MarketingBrief $brief): array => [
                'id' => $brief->id,
                'title' => $brief->title,
                'status' => $brief->status?->value,
                'due_at' => $brief->due_at?->toDateTimeString(),
            ])
            ->all();

        $plans = MarketingPlan::query()
            ->where(function ($query) use ($start, $end): void {
                $query->whereBetween('starts_at', [$start, $end])
                    ->orWhereBetween('ends_at', [$start, $end]);
            })
            ->orderBy('starts_at')
            ->get()
            ->map(fn (MarketingPlan $plan): array => [
                'id' => $plan->id,
                'name' => $plan->name,
                'status' => $plan->status?->value,
                'starts_at' => $plan->starts_at?->toDateTimeString(),
                'ends_at' => $plan->ends_at?->toDateTimeString(),
            ])
            ->all();

        return Inertia::render('marketing/calendar', [
            'month' => $start->format('Y-m'),
            'posts' => $posts,
            'briefs' => $briefs,
            'plans' => $plans,
        ]);
    }
}
