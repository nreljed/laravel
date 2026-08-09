<?php

namespace Tests\Feature\Services;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Nreljed\DashboardCore\Models\Setting;
use Nreljed\DashboardCore\Services\FormattingService;
use Nreljed\DashboardCore\Services\SettingsService;
use Tests\TestCase;

class FormattingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_format_currency_uses_settings_defaults(): void
    {
        $this->assertSame('$1,234.56', $this->formatting()->formatCurrency(1234.56));
    }

    public function test_format_currency_honors_db_settings(): void
    {
        Setting::set('default_currency', 'EUR');
        Setting::set('currency_position', 'after');

        $this->assertSame('1,234.56 €', $this->formatting()->formatCurrency(1234.56));
    }

    public function test_format_currency_options_override_settings(): void
    {
        Setting::set('default_currency', 'EUR');

        $this->assertSame('£1,234.56', $this->formatting()->formatCurrency(1234.56, ['currency' => 'GBP']));
    }

    public function test_format_currency_returns_empty_text_for_zero(): void
    {
        Setting::set('price_empty_text', '—');

        $this->assertSame('—', $this->formatting()->formatCurrency(0));
        $this->assertSame('', $this->formatting()->formatCurrency(0, ['empty_text' => '']));
    }

    public function test_format_price_accepts_explicit_options(): void
    {
        $this->assertSame(
            '1,234.56 €',
            $this->formatting()->formatPrice(1234.56, ['currency' => 'EUR', 'position' => 'after'])
        );
    }

    public function test_get_currency_symbol_returns_symbol_or_code(): void
    {
        $this->assertSame('$', $this->formatting()->getCurrencySymbol('USD'));
        $this->assertSame('XXX', $this->formatting()->getCurrencySymbol('XXX'));
    }

    public function test_settings_service_format_currency_bridge(): void
    {
        $this->assertSame('$1,234.56', SettingsService::formatCurrency(1234.56));
    }

    private function formatting(): FormattingService
    {
        return app(FormattingService::class);
    }
}
