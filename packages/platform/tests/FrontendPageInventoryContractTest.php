<?php

namespace Nreljed\Platform\Tests;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Structural contract test for the platform's frontend page kit.
 *
 * `registerCoreTemplates()` globs `resources/js/Pages` recursively, so any page
 * dropped into that directory silently auto-registers across every consuming
 * app. This test pins the STABLE core page set: adding or removing a page
 * here requires a deliberate, reviewed change. It also forbids theme-owned /
 * template paths (public marketing, blog, shop storefront) from ever being
 * added to the core kit — those live in the starter theme
 * (`@nreljed/platform/themes/starter`) or the app.
 *
 * @coversNothing
 */
final class FrontendPageInventoryContractTest extends TestCase
{
    private string $coreRoot;

    /**
     * The exact, stable inventory of core page files, relative to
     * `resources/js/Pages/`, forward-slash normalized.
     *
     * @var non-empty-list<string>
     */
    private const STABLE_CORE_PAGES = [
        'Auth/Confirm-Password.tsx',
        'Auth/Email-Verified.tsx',
        'Auth/Forgot-Password.tsx',
        'Auth/Login.tsx',
        'Auth/Register.tsx',
        'Auth/RegisterClosed.tsx',
        'Auth/Reset-Password.tsx',
        'Auth/Verify-Email.tsx',
        'dashboard/account/advanced.tsx',
        'dashboard/account/integrations.tsx',
        'dashboard/account/notifications.tsx',
        'dashboard/account/profile.tsx',
        'dashboard/account/security.tsx',
        'dashboard/admin/audit-logs/index.tsx',
        'dashboard/admin/database-maintenance.tsx',
        'dashboard/admin/deploy-history.tsx',
        'dashboard/admin/deploy-status.tsx',
        'dashboard/admin/logs.tsx',
        'dashboard/admin/permissions/manage.tsx',
        'dashboard/admin/roles/form.tsx',
        'dashboard/admin/roles/index.tsx',
        'dashboard/admin/settings/dev.tsx',
        'dashboard/admin/settings/email.tsx',
        'dashboard/admin/settings/general.tsx',
        'dashboard/admin/settings/ip-access.tsx',
        'dashboard/admin/settings/maintenance.tsx',
        'dashboard/admin/settings/modules.tsx',
        'dashboard/admin/settings/notifications.tsx',
        'dashboard/admin/settings/registration.tsx',
        'dashboard/admin/settings/security.tsx',
        'dashboard/admin/system-health.tsx',
        'dashboard/admin/users/form.tsx',
        'dashboard/admin/users/index.tsx',
        'dashboard/admin/users/sessions.tsx',
        'dashboard/developer/style-guide.tsx',
        'dashboard/index.tsx',
        'dashboard/notifications/index.tsx',
        'dashboard/notifications/quick-send-section.tsx',
        'dashboard/notifications/system-status-cards.tsx',
        'dashboard/notifications/test.tsx',
        'dashboard/stats.tsx',
        'Error.tsx',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->coreRoot = dirname(__DIR__);
    }

    public function test_core_page_inventory_matches_the_stable_set(): void
    {
        $actual = $this->corePages();

        sort($actual);
        $expected = self::STABLE_CORE_PAGES;
        sort($expected);

        $this->assertSame(
            $expected,
            $actual,
            'core resources/js/Pages inventory drifted from the pinned set — update '
            .'FrontendPageInventoryContractTest::STABLE_CORE_PAGES deliberately, never '
            .'as a side effect of adding a page.',
        );
    }

    public function test_no_theme_owned_or_template_pages_in_core_kit(): void
    {
        foreach ($this->corePages() as $page) {
            $this->assertDoesNotMatchRegularExpression(
                '#^(Public|Blog|Post|Shop|Storefront|Stores)/#i',
                $page,
                "[{$page}] is a theme-owned/template page — it must live in the app or "
                .'the starter theme (@nreljed/platform/themes/starter), never in core resources/js/Pages.',
            );
        }
    }

    public function test_theme_owned_pages_are_absent_from_the_core_kit_directory(): void
    {
        $root = $this->coreRoot.'/resources/js/Pages';

        foreach (['Public', 'Blog', 'Shop', 'Storefront', 'Stores'] as $forbidden) {
            $this->assertDirectoryDoesNotExist(
                $root.'/'.$forbidden,
                "[{$forbidden}/] must not exist under resources/js/Pages — theme-owned pages "
                .'belong in the starter theme or the app.',
            );
        }
    }

    /**
     * @return list<string>
     */
    private function corePages(): array
    {
        $root = realpath($this->coreRoot.'/resources/js/Pages');

        if ($root === false) {
            $this->fail('core pages directory not found: '.$this->coreRoot.'/resources/js/Pages');
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS),
        );

        $prefix = str_replace('\\', '/', $root).'/';
        $pages = [];

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'tsx') {
                continue;
            }

            $path = str_replace('\\', '/', (string) $file->getPathname());

            if (str_starts_with($path, $prefix)) {
                $pages[] = substr($path, strlen($prefix));
            }
        }

        return $pages;
    }
}
