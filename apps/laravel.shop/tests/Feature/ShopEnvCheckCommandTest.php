<?php

namespace Tests\Feature;

use App\Console\Commands\ShopEnvCheckCommand;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * The environment check has to be able to tell a served tree from a copy that
 * nothing serves, on a machine that may have no LocalWP install at all.
 *
 * That is why the config parsing is a static method: CI has no sites to
 * discover, so the discovery is exercised here against a real site config
 * instead of being left untested until someone is already confused.
 */
class ShopEnvCheckCommandTest extends TestCase
{
    public function test_it_reads_the_app_directory_out_of_a_site_config(): void
    {
        $config = <<<'CONF'
        ServerName shop-laravel.local
        DocumentRoot "C:\Users\nrelj\Local Sites\shop-laravel\app\public"
        <Directory "C:\Users\nrelj\Local Sites\shop-laravel\app\public">
        </Directory>
        CONF;

        $this->assertSame(
            ['C:\Users\nrelj\Local Sites\shop-laravel\app'],
            ShopEnvCheckCommand::appPathsFromSiteConfig($config)
        );
    }

    public function test_it_normalizes_forward_slashes_from_a_site_config(): void
    {
        $config = 'DocumentRoot "C:/Users/nrelj/Local Sites/laravel/app/public"';

        $this->assertSame(
            ['C:\Users\nrelj\Local Sites\laravel\app'],
            ShopEnvCheckCommand::appPathsFromSiteConfig($config)
        );
    }

    public function test_it_ignores_roots_that_are_not_public_directories(): void
    {
        $config = <<<'CONF'
        DocumentRoot "C:\Users\nrelj\Local Sites\shop-laravel\app\public"
        Alias /nothing "C:\Users\nrelj\Local Sites\somewhere\else"
        CONF;

        $this->assertSame(
            ['C:\Users\nrelj\Local Sites\shop-laravel\app'],
            ShopEnvCheckCommand::appPathsFromSiteConfig($config)
        );
    }

    public function test_a_config_with_no_document_root_yields_nothing(): void
    {
        $this->assertSame([], ShopEnvCheckCommand::appPathsFromSiteConfig("ServerName example.test\n"));
    }

    public function test_the_command_reports_a_consistent_environment(): void
    {
        $exitCode = Artisan::call('shop:env-check', ['--json' => true]);
        $output = Artisan::output();

        $payload = json_decode($output, true);

        $this->assertIsArray($payload, "The command did not emit JSON: {$output}");

        $this->assertNotEmpty($payload['app_path']);

        // The one thing that must hold on every machine: no watched package may
        // be a local working tree, or the tests below are exercising code that
        // production will never receive.
        foreach ($payload['packages'] as $name => $package) {
            $this->assertSame(
                'ok',
                $package['status'],
                "{$name} is not a clean released install: {$package['status']}"
            );
        }

        // The exit code has to agree with the problems that were reported, so a
        // clean tree can never report failure and a broken one cannot report
        // success.
        $this->assertSame(
            $payload['problems'] === [] ? 0 : 1,
            $exitCode,
            "Exit code {$exitCode} does not match the reported problems: {$output}"
        );

        // This suite runs in more than one tree, and only one of them is the
        // copy a local site serves: the workspace app is, this deploy
        // repository is not, and CI has no local sites at all. Being the
        // unserved copy is a true report, not a fault, so it is the single
        // problem that may legitimately appear here.
        $unexpected = array_values(array_filter(
            $payload['problems'],
            fn (string $problem): bool => ! str_contains($problem, 'No local site serves this tree')
        ));

        $this->assertSame(
            [],
            $unexpected,
            'The environment check reported problems beyond the unserved-tree notice: '
            .implode(' | ', $unexpected)
        );
    }
}
