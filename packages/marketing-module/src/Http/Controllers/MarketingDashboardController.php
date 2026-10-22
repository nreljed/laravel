<?php

namespace Nreljed\MarketingModule\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use Nreljed\MarketingModule\Enums\PostStatus;
use Nreljed\MarketingModule\Models\Contact;
use Nreljed\MarketingModule\Models\Segment;
use Nreljed\MarketingModule\Models\SocialAccount;
use Nreljed\MarketingModule\Models\SocialPost;
use Nreljed\MarketingModule\Models\Tag;
use Nreljed\MarketingModule\Services\SocialChannelRegistry;
use Nreljed\Platform\Http\Controllers\Controller;
use Nreljed\Platform\Models\User;

/**
 * The marketing landing page.
 *
 * It answers one question — "what can this install actually publish to?" —
 * so it lists the connected accounts first and the audience sizes second.
 */
class MarketingDashboardController extends Controller
{
    public function index(SocialChannelRegistry $channels): Response
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 403);
        abort_unless($this->canView($user), 403);

        return Inertia::render('marketing/dashboard', [
            'summary' => [
                'accounts' => SocialAccount::query()->where('user_id', $user->id)->count(),
                'contacts' => Contact::query()->count(),
                'tags' => Tag::query()->count(),
                'segments' => Segment::query()->count(),
                'posts' => SocialPost::query()->count(),
                'scheduled' => SocialPost::query()
                    ->where('status', PostStatus::Scheduled->value)
                    ->count(),
            ],
            'accounts' => SocialAccount::query()
                ->where('user_id', $user->id)
                ->orderBy('platform')
                ->get()
                ->map(fn (SocialAccount $account): array => [
                    'id' => $account->id,
                    'platform' => $account->platform,
                    'account_name' => $account->account_name,
                    'username' => $account->username,
                    'status' => $account->status?->value,
                    'last_validated_at' => $account->last_validated_at?->toDateTimeString(),
                ])
                ->all(),
            'platforms' => $channels->platforms(),
        ]);
    }

    /**
     * Any marketing permission is enough to look at the landing page. The
     * sidebar item and the page itself must agree, or the user gets a link
     * that 403s.
     */
    protected function canView(User $user): bool
    {
        foreach (['accounts', 'posts', 'contacts', 'tags', 'segments'] as $area) {
            if ($user->hasPermission("marketing.{$area}.manage")) {
                return true;
            }
        }

        return false;
    }
}
