<?php

namespace Tests\Feature\Services;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Nreljed\Platform\Models\Setting;
use Nreljed\Platform\Services\EmailSettingsService;
use Tests\TestCase;

class EmailSettingsServiceCacheTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_get_options_serves_stored_values(): void
    {
        Setting::updateOrCreate(
            ['key' => 'email_settings'],
            ['value' => json_encode(['smtp_host' => 'mail.example.com', 'smtp_port' => 2525])]
        );

        $options = app(EmailSettingsService::class)->getOptions();

        $this->assertSame('mail.example.com', $options['smtp_host']);
        $this->assertSame(2525, $options['smtp_port']);
    }

    public function test_get_options_is_cached_within_ttl(): void
    {
        Setting::updateOrCreate(
            ['key' => 'email_settings'],
            ['value' => json_encode(['smtp_host' => 'cached.example.com'])]
        );

        $service = app(EmailSettingsService::class);

        $this->assertSame('cached.example.com', $service->getOptions()['smtp_host']);

        // Mutate the row directly, as another process would; the cache is now stale.
        Setting::where('key', 'email_settings')
            ->update(['value' => json_encode(['smtp_host' => 'direct.example.com'])]);

        $this->assertSame('cached.example.com', $service->getOptions()['smtp_host']);
    }

    public function test_update_from_request_persists_and_invalidates_cache(): void
    {
        $service = app(EmailSettingsService::class);

        $result = $service->updateFromRequest([
            'smtp_host' => 'smtp.example.com',
            'smtp_port' => '587',
            'from_email' => 'no-reply@example.com',
        ]);

        $this->assertSame('smtp.example.com', $result['smtp_host']);
        $this->assertSame(587, $result['smtp_port']);
        $this->assertSame('no-reply@example.com', $result['from_email']);

        $stored = json_decode(Setting::where('key', 'email_settings')->value('value'), true);
        $this->assertSame('smtp.example.com', $stored['smtp_host']);
        $this->assertSame(587, $stored['smtp_port']);
    }

    public function test_update_from_request_after_external_change_reads_fresh(): void
    {
        $service = app(EmailSettingsService::class);

        // Prime the cache.
        $service->updateFromRequest(['smtp_host' => 'first.example.com']);
        $this->assertSame('first.example.com', $service->getOptions()['smtp_host']);

        // External mutation leaves the cache stale.
        Setting::where('key', 'email_settings')
            ->update(['value' => json_encode(['smtp_host' => 'stale.example.com'])]);

        $result = $service->updateFromRequest(['smtp_host' => 'final.example.com']);

        $this->assertSame('final.example.com', $result['smtp_host']);
    }
}
