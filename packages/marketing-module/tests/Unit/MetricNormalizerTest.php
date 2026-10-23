<?php

namespace Nreljed\MarketingModule\Tests\Unit;

use Nreljed\MarketingModule\Services\MetricNormalizer;
use PHPUnit\Framework\TestCase;

final class MetricNormalizerTest extends TestCase
{
    public function test_it_maps_aliases_and_keeps_real_zeroes(): void
    {
        $normalized = (new MetricNormalizer)->normalize([
            'post_impressions' => 100,
            'reactions' => 0,
            'comment_count' => 3,
            'saved' => 0,
            'post_clicks' => 7,
        ]);

        $this->assertSame(100, $normalized['metrics']['impressions']);
        $this->assertSame(0, $normalized['metrics']['likes']);
        $this->assertSame(3, $normalized['metrics']['comments']);
        $this->assertSame(0, $normalized['metrics']['saves']);
        $this->assertSame(7, $normalized['metrics']['clicks']);
        $this->assertNull($normalized['metrics']['reach']);
        $this->assertSame([], $normalized['custom']);
    }

    public function test_it_prefers_an_exact_key_over_an_alias(): void
    {
        $normalized = (new MetricNormalizer)->normalize([
            'likes' => 9,
            'reactions' => 2,
        ]);

        $this->assertSame(9, $normalized['metrics']['likes']);
    }

    public function test_it_keeps_unknown_numeric_keys_in_custom(): void
    {
        $normalized = (new MetricNormalizer)->normalize([
            'impressions' => 12,
            'pin_saves' => 4,
            'label' => 'not-a-number',
        ]);

        $this->assertSame(12, $normalized['metrics']['impressions']);
        $this->assertSame(['pin_saves' => 4], $normalized['custom']);
        $this->assertSame('not-a-number', $normalized['raw']['label']);
    }

    public function test_it_maps_pinterest_and_meta_metric_names(): void
    {
        $normalized = (new MetricNormalizer)->normalize([
            'IMPRESSION' => 20,
            'post_impressions_unique' => 12,
            'SAVE' => 2,
            'OUTBOUND_CLICK' => 3,
            'REPIN' => 1,
            'COMMENT' => 4,
            'VIDEO_VIEW' => 5,
            'PIN_CLICK' => 6,
        ]);

        $this->assertSame(20, $normalized['metrics']['impressions']);
        $this->assertSame(12, $normalized['metrics']['reach']);
        $this->assertSame(2, $normalized['metrics']['saves']);
        $this->assertSame(3, $normalized['metrics']['clicks']);
        $this->assertSame(1, $normalized['metrics']['shares']);
        $this->assertSame(4, $normalized['metrics']['comments']);
        $this->assertSame(5, $normalized['metrics']['video_views']);
        $this->assertSame(['pin_click' => 6], $normalized['custom']);
    }

    public function test_it_strips_credential_like_keys_from_raw(): void
    {
        $normalized = (new MetricNormalizer)->normalize([
            'impressions' => 5,
            'access_token' => 'secret',
            'nested' => [
                'refresh_token' => 'secret',
                'client_secret' => 'secret',
                'likes' => 1,
            ],
        ]);

        $this->assertArrayNotHasKey('access_token', $normalized['raw']);
        $this->assertArrayNotHasKey('refresh_token', $normalized['raw']['nested']);
        $this->assertArrayNotHasKey('client_secret', $normalized['raw']['nested']);
        $this->assertSame(1, $normalized['raw']['nested']['likes']);
    }
}
