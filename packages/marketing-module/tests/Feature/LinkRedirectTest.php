<?php

namespace Nreljed\MarketingModule\Tests\Feature;

use Illuminate\Http\Request;
use Nreljed\MarketingModule\Http\Controllers\LinkRedirectController;
use Nreljed\MarketingModule\Models\MarketingLink;
use Nreljed\MarketingModule\Models\MarketingLinkClick;
use Nreljed\MarketingModule\Models\SocialAccount;
use Nreljed\MarketingModule\Models\SocialPost;
use Nreljed\MarketingModule\Models\SocialPostTarget;
use Nreljed\MarketingModule\Services\TrackedLinkService;
use Nreljed\MarketingModule\Tests\Concerns\BootsDatabase;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class LinkRedirectTest extends TestCase
{
    use BootsDatabase;

    private TrackedLinkService $links;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootDatabase();

        $this->container['db']->table('users')->insert([
            'id' => 1,
            'name' => 'Author',
            'email' => 'author@example.test',
        ]);

        $this->links = new TrackedLinkService;
    }

    public function test_it_redirects_to_the_stored_destination_and_records_a_click(): void
    {
        $link = $this->link();
        $request = Request::create('/m/'.$link->short_code.'?next=https://evil.test', 'GET', server: [
            'REMOTE_ADDR' => '198.51.100.7',
            'HTTP_USER_AGENT' => 'Test agent',
        ]);

        $response = (new LinkRedirectController)($request, $link->short_code, $this->links);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame(
            'https://shop.test/offer?utm_source=instagram&utm_medium=social&utm_content=post-'.$link->social_post_id.'-instagram',
            $response->getTargetUrl(),
        );
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertSame('no-referrer', $response->headers->get('Referrer-Policy'));
        $this->assertSame(1, MarketingLinkClick::query()->count());
        $this->assertSame(1, $link->refresh()->clicks_count);
    }

    public function test_an_unknown_code_is_not_found(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $request = Request::create('/m/missing', 'GET');

        (new LinkRedirectController)($request, 'missing', $this->links);
    }

    public function test_a_disabled_link_is_not_found(): void
    {
        $link = $this->link();
        $link->forceFill(['is_active' => false])->save();

        $this->expectException(NotFoundHttpException::class);

        $request = Request::create('/m/'.$link->short_code, 'GET');

        (new LinkRedirectController)($request, $link->short_code, $this->links);
    }

    private function link(): MarketingLink
    {
        $post = SocialPost::create([
            'user_id' => 1,
            'body' => 'Hello world',
            'link' => 'https://shop.test/offer',
            'track_clicks' => true,
            'status' => 'draft',
            'timezone' => 'UTC',
            'requires_approval' => false,
        ]);

        $account = SocialAccount::create([
            'user_id' => 1,
            'platform' => 'instagram',
            'account_id' => 'instagram-1',
            'account_name' => 'Instagram account',
            'scopes' => [],
            'status' => 'active',
            'metadata' => [],
        ]);

        $target = SocialPostTarget::create([
            'social_post_id' => $post->id,
            'social_account_id' => $account->id,
        ]);

        $link = $this->links->ensureForTarget($target, $post);

        $this->assertNotNull($link);

        return $link;
    }
}
