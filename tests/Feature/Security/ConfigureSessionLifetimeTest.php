<?php

namespace Tests\Feature\Security;

use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConfigureSessionLifetimeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('session.lifetime', 120);
        SettingsService::clearCache();
    }

    public function test_session_lifetime_from_settings_is_applied(): void
    {
        SettingsService::set('session_lifetime', '45');

        $this->get('/');

        $this->assertEquals(45, (int) config('session.lifetime'));
    }

    public function test_default_lifetime_is_applied_when_no_setting(): void
    {
        $this->get('/');

        $this->assertEquals(120, (int) config('session.lifetime'));
    }

    public function test_zero_or_negative_lifetime_does_not_override(): void
    {
        SettingsService::set('session_lifetime', '0');

        $this->get('/');

        $this->assertNotEquals(0, (int) config('session.lifetime'));
    }
}
