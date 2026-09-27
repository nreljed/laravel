<?php

namespace Nreljed\ShopModule\Tests;

use Nreljed\ShopModule\ShopServiceProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Guards the separation between "may browse the store" and "may administer
 * the shop".
 *
 * `shop.view` is granted to the base `user` role in consuming apps so
 * customers can reach their order portal. While the `/dashboard/shop` route
 * group was gated on that same permission, every registered shopper was
 * eligible to enter the whole admin prefix - reaching a customer search that
 * returns arbitrary users' names and emails, and an order list.
 *
 * `shop.manage` is the boundary. The route group, the settings group and the
 * navigation split all key off it, while `shop.view` keeps its narrower
 * meaning for the customer portal.
 */
final class ShopAdminAccessContractTest extends TestCase
{
    private string $source;

    protected function setUp(): void
    {
        parent::setUp();

        $this->source = file_get_contents(dirname(__DIR__).'/src/ShopServiceProvider.php');
    }

    public function test_manage_permission_is_declared(): void
    {
        $slugs = array_column($this->provider()->permissions(), 'slug');

        $this->assertContains('shop.manage', $slugs);
    }

    public function test_view_permission_survives_for_the_customer_portal(): void
    {
        $slugs = array_column($this->provider()->permissions(), 'slug');

        $this->assertContains('shop.view', $slugs, 'shop.view still gates the customer order portal, so it must remain declared.');
    }

    public function test_shop_admin_route_group_is_gated_on_manage_not_view(): void
    {
        $this->assertMatchesRegularExpression(
            "#permission:shop\.manage'\]\)\s*\n\s*->prefix\('/dashboard/shop'\)#",
            $this->source,
            'The /dashboard/shop group must be gated on shop.manage.'
        );
    }

    public function test_no_route_group_gates_the_admin_prefix_on_view(): void
    {
        $this->assertDoesNotMatchRegularExpression(
            "#permission:shop\.view'\]\)\s*\n\s*->prefix\('/(dashboard|admin)/shop'\)#",
            $this->source,
            'No /dashboard/shop or /admin/shop group may be gated on shop.view - that is what let customers in.'
        );
    }

    public function test_settings_group_requires_both_admin_and_manage(): void
    {
        $this->assertMatchesRegularExpression(
            "#'admin',\s*'permission:shop\.manage'\]\)\s*\n\s*->prefix\('/admin/shop'\)#",
            $this->source,
            'Shop settings must keep the admin middleware alongside shop.manage.'
        );
    }

    public function test_navigation_split_keys_off_manage(): void
    {
        $this->assertMatchesRegularExpression(
            "#Shop staff: show only Shop management.*?if \(\\\$user->can\('shop\.manage'\)\) \{#s",
            $this->source,
            'The management navigation branch must be selected by shop.manage, not shop.products.create.'
        );
    }

    public function test_management_group_label_keys_off_manage(): void
    {
        $this->assertMatchesRegularExpression(
            "#if \(\\\$user->can\('shop\.manage'\)\) \{\s*\n\s*return 'Manage Shop';#",
            $this->source,
            'The Manage Shop group label must be selected by shop.manage.'
        );
    }

    public function test_customer_branch_still_keys_off_view(): void
    {
        $this->assertMatchesRegularExpression(
            "#// Customer: show only my account portal.*?if \(\\\$user->can\('shop\.view'\)\)#s",
            $this->source,
            'The customer navigation branch must remain gated on shop.view so shoppers keep their order portal.'
        );
    }

    private function provider(): ShopServiceProvider
    {
        return (new ReflectionClass(ShopServiceProvider::class))->newInstanceWithoutConstructor();
    }
}
