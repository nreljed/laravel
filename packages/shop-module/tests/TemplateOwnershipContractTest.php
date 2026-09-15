<?php

namespace Nreljed\ShopModule\Tests;

use Nreljed\ShopModule\ShopServiceProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use SplFileInfo;

/**
 * Structural guard for the owner-aware email template resolution:
 *
 *   - every template key declared in ShopServiceProvider::getEmailTemplates()
 *     MUST have a physical blade under resources/views/email/templates/ so the
 *     core EmailTemplateService owner map resolves it to shop::... (not the
 *     core default).
 *   - every notification type with an email_template key must also be declared
 *     so sends actually render (not fall through to the core default).
 *   - the module MUST NOT use View::addLocation() to sneak views into the app
 *     path; it declares its own shop:: view namespace instead.
 */
final class TemplateOwnershipContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = dirname(__DIR__);
    }

    public function test_every_declared_template_has_a_matching_blade(): void
    {
        $declared = $this->provider()->getEmailTemplates();
        $shipped = $this->shippedTemplateKeys();

        $missing = array_values(array_diff(array_keys($declared), $shipped));

        $this->assertSame([], $missing, 'Templates declared in getEmailTemplates() but no blade shipped under resources/views/email/templates/:'.PHP_EOL.implode(PHP_EOL, $missing));
    }

    public function test_every_shipped_email_template_is_declared(): void
    {
        $declared = $this->provider()->getEmailTemplates();
        $shipped = $this->shippedTemplateKeys();

        $undeclared = array_values(array_diff($shipped, array_keys($declared)));

        $this->assertSame([], $undeclared, 'Blades shipped under resources/views/email/templates/ but not declared in getEmailTemplates():'.PHP_EOL.implode(PHP_EOL, $undeclared));
    }

    public function test_every_notification_email_template_is_declared(): void
    {
        $declared = array_keys($this->provider()->getEmailTemplates());

        $referenced = [];

        foreach ($this->provider()->getNotificationTypes() as $type) {
            if (isset($type['email_template'])) {
                $referenced[] = $type['email_template'];
            }
        }

        $referenced = array_values(array_unique($referenced));

        $undeclared = array_values(array_diff($referenced, $declared));

        $this->assertSame([], $undeclared, 'Notification types reference email templates not declared in getEmailTemplates():'.PHP_EOL.implode(PHP_EOL, $undeclared));
    }

    public function test_module_does_not_use_view_addlocation_hack(): void
    {
        $violations = [];

        foreach ($this->phpFiles() as $file) {
            $content = file_get_contents($file);

            if (preg_match('/View::addLocation\s*\(/', (string) $content)) {
                $violations[] = substr($file, strlen($this->root.'/'));
            }
        }

        $this->assertSame([], $violations, 'View::addLocation() routes the module into the app view path - use loadViewsFrom(...,"shop") and namespaced shop::view calls instead:'.PHP_EOL.implode(PHP_EOL, $violations));
    }

    public function test_module_uses_namespaced_views_for_its_own_email_blades(): void
    {
        $violations = [];

        foreach ($this->phpFiles() as $file) {
            $content = file_get_contents($file);

            if (preg_match('#view\(\s*[\'"](?!shop::)[^"\']*email[^"\']*[\'"]#', (string) $content)) {
                $violations[] = sprintf('%s references an un-namespaced email view - use shop::email...', substr($file, strlen($this->root.'/')));
            }
        }

        $this->assertSame([], $violations, 'Un-namespaced email view references:'.PHP_EOL.implode(PHP_EOL, $violations));
    }

    private function provider(): ShopServiceProvider
    {
        $reflection = new ReflectionClass(ShopServiceProvider::class);

        /** @var ShopServiceProvider $provider */
        return $reflection->newInstanceWithoutConstructor();
    }

    /**
     * @return list<string>
     */
    private function shippedTemplateKeys(): array
    {
        $dir = $this->root.'/resources/views/email/templates';

        if (! is_dir($dir)) {
            return [];
        }

        $keys = [];

        foreach (glob($dir.'/*.blade.php') ?: [] as $file) {
            $keys[] = basename($file, '.blade.php');
        }

        sort($keys);

        return $keys;
    }

    /**
     * @return list<string>
     */
    private function phpFiles(): array
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->root.'/src')
        );

        $files = [];

        foreach ($iterator as $file) {
            /** @var SplFileInfo $file */
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }
}
