<?php

namespace Tests\Feature\Modules\Shop;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Nreljed\Platform\Models\Permission;
use Nreljed\Platform\Models\Role;
use Nreljed\Platform\Services\FeatureRegistry;
use Tests\BootsFeatures;
use Tests\TestCase;

class ShopPermissionMatrixTest extends TestCase
{
    use BootsFeatures, RefreshDatabase;

    private array $shopPermSlugs = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootFeatures();

        // Collect all shop.* permission slugs from the registry
        $registry = app(FeatureRegistry::class);
        foreach ($registry->allProviders() as $module) {
            if ($module->slug() === 'shop') {
                foreach ($module->permissions() as $perm) {
                    $this->shopPermSlugs[] = $perm['slug'];
                }
            }
        }
    }

    public function test_all_shop_permissions_exist_in_database(): void
    {
        $expectedSlugs = [
            'shop.view',
            'shop.products.create',
            'shop.products.edit',
            'shop.products.delete',
            'shop.orders.view',
            'shop.orders.edit',
            'shop.orders.refund',
            'shop.tags.manage',
            'shop.brands.manage',
            'shop.coupons.manage',
            'shop.reviews.moderate',
            'shop.shipping.manage',
            'shop.taxes.manage',
            'shop.webhooks.manage',
            'shop.currencies.manage',
        ];

        foreach ($expectedSlugs as $slug) {
            $this->assertTrue(
                Permission::where('slug', $slug)->exists(),
                "Permission {$slug} missing from DB"
            );
        }
    }

    public function test_admin_role_has_all_shop_permissions(): void
    {
        $role = Role::where('slug', 'admin')->first();
        $role->load('permissions');
        $slugs = $role->permissions->pluck('slug')->toArray();

        foreach ($this->shopPermSlugs as $slug) {
            $this->assertContains($slug, $slugs, "Admin missing permission: {$slug}");
        }
    }

    public function test_shop_owner_role_has_all_shop_permissions(): void
    {
        $role = Role::where('slug', 'shop_owner')->first();
        $role->load('permissions');
        $slugs = $role->permissions->pluck('slug')->toArray();

        foreach ($this->shopPermSlugs as $slug) {
            $this->assertContains($slug, $slugs, "Shop Owner missing permission: {$slug}");
        }
    }

    public function test_shop_owner_does_not_have_admin_permissions(): void
    {
        $role = Role::where('slug', 'shop_owner')->first();
        $role->load('permissions');
        $slugs = $role->permissions->pluck('slug')->toArray();

        $adminOnlySlugs = ['manage-users', 'manage-settings', 'manage-roles', 'manage_options', 'view-system-health'];
        foreach ($adminOnlySlugs as $slug) {
            $this->assertNotContains($slug, $slugs, "Shop Owner should NOT have permission: {$slug}");
        }
    }

    public function test_shop_manager_has_view_edit_shop_permissions(): void
    {
        $role = Role::where('slug', 'shop_manager')->first();
        $role->load('permissions');
        $slugs = $role->permissions->pluck('slug')->toArray();

        // Shop manager can view the shop section and edit existing content
        $allowed = ['shop.view', 'shop.products.edit', 'shop.orders.view', 'shop.orders.edit', 'posts.view', 'posts.edit'];
        foreach ($allowed as $slug) {
            $this->assertContains($slug, $slugs, "Shop Manager missing permission: {$slug}");
        }

        // But NOT manage/delete/refund operations
        $forbidden = ['shop.products.create', 'shop.products.delete', 'shop.orders.refund', 'shop.tags.manage', 'shop.brands.manage', 'shop.coupons.manage', 'shop.reviews.moderate', 'shop.shipping.manage', 'shop.taxes.manage', 'shop.webhooks.manage', 'shop.currencies.manage'];
        foreach ($forbidden as $slug) {
            $this->assertNotContains($slug, $slugs, "Shop Manager should NOT have permission: {$slug}");
        }
    }

    public function test_shop_manager_has_core_permissions(): void
    {
        $role = Role::where('slug', 'shop_manager')->first();
        $role->load('permissions');
        $slugs = $role->permissions->pluck('slug')->toArray();

        $coreSlugs = ['view-dashboard', 'view-profile', 'edit-profile', 'view-notifications', 'manage-notifications'];
        foreach ($coreSlugs as $slug) {
            $this->assertContains($slug, $slugs, "Shop Manager missing core permission: {$slug}");
        }
    }

    public function test_customer_role_has_no_shop_permissions(): void
    {
        $role = Role::where('slug', 'customer')->first();
        $role->load('permissions');
        $slugs = $role->permissions->pluck('slug')->toArray();

        foreach ($this->shopPermSlugs as $slug) {
            $this->assertNotContains($slug, $slugs, "Customer should NOT have permission: {$slug}");
        }
    }

    public function test_customer_role_has_only_core_permissions(): void
    {
        $role = Role::where('slug', 'customer')->first();
        $role->load('permissions');
        $slugs = $role->permissions->pluck('slug')->toArray();

        $expected = ['view-dashboard', 'view-profile', 'edit-profile', 'delete-account', 'view-notifications', 'manage-notifications'];
        foreach ($expected as $slug) {
            $this->assertContains($slug, $slugs, "Customer missing core permission: {$slug}");
        }

        $forbidden = ['manage-users', 'manage-settings', 'manage-roles', 'manage_options', 'view-system-health'];
        foreach ($forbidden as $slug) {
            $this->assertNotContains($slug, $slugs, "Customer should NOT have permission: {$slug}");
        }
    }

    public function test_user_permission_check_works_for_admin(): void
    {
        $user = $this->createUserWithRole('admin');

        foreach ($this->shopPermSlugs as $slug) {
            $this->assertTrue($user->hasPermission($slug), "Admin user missing permission: {$slug}");
        }
    }

    public function test_user_permission_check_works_for_shop_owner(): void
    {
        $user = $this->createUserWithRole('shop_owner');

        foreach ($this->shopPermSlugs as $slug) {
            $this->assertTrue($user->hasPermission($slug), "Shop Owner user missing permission: {$slug}");
        }

        $this->assertFalse($user->hasPermission('manage-users'));
        $this->assertFalse($user->hasPermission('manage-roles'));
        $this->assertFalse($user->hasPermission('manage_options'));
    }

    public function test_user_permission_check_works_for_shop_manager(): void
    {
        $user = $this->createUserWithRole('shop_manager');

        // Shop manager can view the shop section and edit existing content
        $allowed = ['shop.view', 'shop.products.edit', 'shop.orders.view', 'shop.orders.edit', 'posts.view', 'posts.edit'];
        foreach ($allowed as $slug) {
            $this->assertTrue($user->hasPermission($slug), "Shop Manager user missing permission: {$slug}");
        }

        // But NOT manage/delete/refund operations
        $forbidden = ['shop.products.create', 'shop.products.delete', 'shop.orders.refund', 'shop.tags.manage', 'shop.brands.manage', 'shop.coupons.manage', 'shop.reviews.moderate', 'shop.shipping.manage', 'shop.taxes.manage', 'shop.webhooks.manage', 'shop.currencies.manage'];
        foreach ($forbidden as $slug) {
            $this->assertFalse($user->hasPermission($slug), "Shop Manager user should NOT have permission: {$slug}");
        }

        $this->assertTrue($user->hasPermission('view-dashboard'));
        $this->assertTrue($user->hasPermission('view-profile'));
    }

    public function test_user_permission_check_works_for_customer(): void
    {
        $user = $this->createUserWithRole('customer');

        foreach ($this->shopPermSlugs as $slug) {
            $this->assertFalse($user->hasPermission($slug), "Customer user should NOT have permission: {$slug}");
        }

        $this->assertTrue($user->hasPermission('view-dashboard'));
        $this->assertTrue($user->hasPermission('view-profile'));
    }

    public function test_is_admin_only_returns_true_for_admin_role(): void
    {
        $admin = $this->createUserWithRole('admin');
        $this->assertTrue($admin->isAdmin());

        $owner = $this->createUserWithRole('shop_owner');
        $this->assertFalse($owner->isAdmin());

        $manager = $this->createUserWithRole('shop_manager');
        $this->assertFalse($manager->isAdmin());

        $customer = $this->createUserWithRole('customer');
        $this->assertFalse($customer->isAdmin());
    }

    public function test_gate_before_allows_admin_all_abilities(): void
    {
        $admin = $this->createUserWithRole('admin');

        foreach ($this->shopPermSlugs as $slug) {
            $this->assertTrue(Gate::forUser($admin)->allows($slug), "Admin gate denied: {$slug}");
        }

        $this->assertTrue(Gate::forUser($admin)->allows('manage-users'));
        $this->assertTrue(Gate::forUser($admin)->allows('nonexistent-ability'));
    }

    public function test_gate_before_does_not_bypass_for_non_admin(): void
    {
        $customer = $this->createUserWithRole('customer');

        // Customer has no shop perms, so gate should deny
        foreach ($this->shopPermSlugs as $slug) {
            $this->assertFalse(Gate::forUser($customer)->allows($slug), "Customer gate should deny: {$slug}");
        }
    }
}
