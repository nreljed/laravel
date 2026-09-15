<?php

namespace Tests\Feature\Modules\Shop;

use Nreljed\Platform\Services\EmailTemplateService;
use Tests\TestCase;

class EmailTemplateOwnershipTest extends TestCase
{
    private function resolved(string $template): string
    {
        $service = app(EmailTemplateService::class);

        $method = new \ReflectionMethod($service, 'resolveViewName');
        $method->setAccessible(true);

        return $method->invoke($service, $template);
    }

    public function test_core_actions_resolve_to_core_namespace(): void
    {
        $this->assertSame('core::email.templates.new-user', $this->resolved('new-user'));
        $this->assertSame('core::email.templates.verify-email', $this->resolved('verify-email'));
        $this->assertSame('core::email.templates.password-reset', $this->resolved('password-reset'));
        $this->assertSame('core::email.templates.password-changed', $this->resolved('password-changed'));
        $this->assertSame('core::email.templates.account-deleted', $this->resolved('account-deleted'));
        $this->assertSame('core::email.templates.email-changed', $this->resolved('email-changed'));
        $this->assertSame('core::email.templates.test', $this->resolved('test'));
    }

    public function test_shop_actions_resolve_to_shop_namespace(): void
    {
        $this->assertSame('shop::email.templates.order-confirmation', $this->resolved('order-confirmation'));
        $this->assertSame('shop::email.templates.order-shipped', $this->resolved('order-shipped'));
        $this->assertSame('shop::email.templates.low-stock-alert', $this->resolved('low-stock-alert'));
        $this->assertSame('shop::email.templates.cart-recovery-1h', $this->resolved('cart-recovery-1h'));
        $this->assertSame('shop::email.templates.cart-recovery-24h', $this->resolved('cart-recovery-24h'));
        $this->assertSame('shop::email.templates.cart-recovery-72h', $this->resolved('cart-recovery-72h'));
        $this->assertSame('shop::email.templates.order-invoice', $this->resolved('order-invoice'));
    }

    public function test_unknown_template_falls_back_to_core_default(): void
    {
        $this->assertSame('core::email.templates.default', $this->resolved('nonexistent-template-key'));
    }

    public function test_owner_map_contains_all_shop_declared_keys(): void
    {
        $service = app(EmailTemplateService::class);

        $ownersMethod = new \ReflectionMethod($service, 'owners');
        $ownersMethod->setAccessible(true);
        $owners = $ownersMethod->invoke($service);

        $expectedShopKeys = [
            'order-confirmation',
            'order-shipped',
            'low-stock-alert',
            'cart-recovery-1h',
            'cart-recovery-24h',
            'cart-recovery-72h',
            'order-invoice',
        ];

        foreach ($expectedShopKeys as $key) {
            $this->assertSame(
                'shop',
                $owners[$key] ?? null,
                "Template key '$key' should be owned by shop:: but was not in the owner map."
            );
        }
    }
}
