<?php

namespace Nreljed\Platform\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Contract test for maintenance-mode lockout safety.
 *
 * Maintenance mode used to be a trap: admins bypass the middleware, but only
 * while their session lasts (120 minutes by default). `/login` carried
 * `maintenance` too, so once the session expired the owner could not sign back
 * in, and the documented escape hatch - the emailed recovery link - was
 * `Mail::queue()`d into a database queue that a shared host has no worker for.
 * Net effect: your own maintenance flag locks you out of your own admin, with
 * the bypass token readable only from the admin UI you can no longer open.
 *
 * Three separate things have to hold for that not to be true, so all three are
 * pinned here. Framework-free on purpose (the package suite boots no
 * application): these are source-level guards, and the consuming app asserts
 * the runtime behaviour in its own MaintenanceModeTest.
 */
final class MaintenanceLockoutContractTest extends TestCase
{
    /** Routes that must keep working while maintenance mode is on. */
    private const REACHABLE = [
        'login',
        'forgot-password',
        'reset-password/{token}',
    ];

    public function test_the_way_back_in_is_not_gated_by_maintenance(): void
    {
        foreach (self::REACHABLE as $uri) {
            $middleware = $this->middlewareFor($uri);

            $this->assertNotEmpty(
                $middleware,
                "Route [{$uri}] was not found in routes/auth.php - the parser or the route changed.",
            );

            $this->assertContains(
                'guest',
                $middleware,
                "Route [{$uri}] should still be guest-only.",
            );

            $this->assertNotContains(
                'maintenance',
                $middleware,
                "Route [{$uri}] is behind maintenance mode again: an admin whose session "
                .'expires during a maintenance window could not sign back in.',
            );
        }
    }

    public function test_signup_stays_closed_during_maintenance(): void
    {
        $middleware = $this->middlewareFor('register');

        $this->assertNotEmpty($middleware, 'Route [register] was not found in routes/auth.php.');
        $this->assertContains(
            'maintenance',
            $middleware,
            'Registration escaped the maintenance gate: accounts created while the site is '
            .'closed cannot be used anyway.',
        );
    }

    public function test_recovery_mail_is_sent_synchronously(): void
    {
        $source = $this->source('src/Http/Controllers/RecoveryController.php');

        // A queued recovery link is no recovery link at all on a host with no
        // queue worker - it would sit in the `jobs` table forever while the
        // admin stays locked out.
        $this->assertStringNotContainsString(
            '->queue(new RecoveryMail',
            $source,
            'RecoveryMail must be sent, not queued: queued mail never leaves a host '
            .'without a queue worker, and this mail is the lockout lifeline.',
        );

        $this->assertSame(
            2,
            substr_count($source, '->send(new RecoveryMail'),
            'Expected both recovery entry points (requestToken + sendRecoveryEmails) to '
            .'send RecoveryMail synchronously.',
        );
    }

    public function test_the_scheduler_drains_the_queue(): void
    {
        $source = $this->source('src/PlatformServiceProvider.php');

        $this->assertStringContainsString(
            "'queue:work'",
            $source,
            'The scheduler no longer drains the queue: on a shared host the database queue '
            .'is never worked, so every Mail::queue()/Job dispatch is silently lost.',
        );

        $this->assertStringContainsString(
            '--stop-when-empty',
            $source,
            'The scheduled queue worker must exit when the queue is empty, otherwise it would '
            .'hold the scheduler slot for the whole --max-time window on every tick.',
        );

        // The flag is valueless. Passing it as `'--stop-when-empty' => true`
        // renders `--stop-when-empty="1"`, which Symfony rejects with "The
        // --stop-when-empty option does not accept a value" - the drain would
        // fail on every tick, which is worse than no drain at all because it
        // looks configured.
        $this->assertMatchesRegularExpression(
            "/'queue:work',\\s*\\[\\s*'--stop-when-empty',/",
            $source,
            'Pass --stop-when-empty as a list item, not as `=> true`: the schedule renders '
            .'`=> true` flags as --flag="1", which Symfony rejects.',
        );

        $this->assertMatchesRegularExpression(
            "/'queue:work'[\s\S]{0,400}->everyMinute\(\)/",
            $source,
            'The queue drain should run every minute so queued mail goes out promptly.',
        );
    }

    /**
     * Middleware attached to the group that registers the given URI.
     *
     * @return array<int, string>
     */
    private function middlewareFor(string $uri): array
    {
        foreach ($this->middlewareGroups($this->source('routes/auth.php')) as $group) {
            $pattern = "/Route::(?:get|post|put|patch|delete)\(\s*'".preg_quote($uri, '/')."'/";

            if (preg_match($pattern, $group['body']) === 1) {
                return $group['middleware'];
            }
        }

        return [];
    }

    /**
     * Every `Route::middleware([...])->group(function () { ... })` in a file,
     * with its middleware list and body.
     *
     * @return array<int, array{middleware: array<int, string>, body: string}>
     */
    private function middlewareGroups(string $source): array
    {
        $groups = [];
        $offset = 0;

        while (($start = strpos($source, 'Route::middleware(', $offset)) !== false) {
            $arrayStart = strpos($source, '[', $start);
            $arrayEnd = $arrayStart === false ? null : $this->matching($source, $arrayStart, '[', ']');

            if ($arrayStart === false || $arrayEnd === null) {
                break;
            }

            $middleware = array_values(array_filter(array_map(
                fn (string $item): string => trim($item, " \t\n\r\0\x0B'\""),
                explode(',', substr($source, $arrayStart + 1, $arrayEnd - $arrayStart - 1)),
            )));

            $bodyStart = strpos($source, '{', $arrayEnd);
            $bodyEnd = $bodyStart === false ? null : $this->matching($source, $bodyStart, '{', '}');

            if ($bodyStart === false || $bodyEnd === null) {
                break;
            }

            $groups[] = [
                'middleware' => $middleware,
                'body' => substr($source, $bodyStart, $bodyEnd - $bodyStart),
            ];

            $offset = $bodyEnd;
        }

        return $groups;
    }

    /**
     * Position of the bracket that closes the one at $position, or null.
     */
    private function matching(string $source, int $position, string $open, string $close): ?int
    {
        $depth = 0;
        $length = strlen($source);

        for ($i = $position; $i < $length; $i++) {
            if ($source[$i] === $open) {
                $depth++;
            } elseif ($source[$i] === $close) {
                $depth--;

                if ($depth === 0) {
                    return $i;
                }
            }
        }

        return null;
    }

    private function source(string $relative): string
    {
        $path = dirname(__DIR__).'/'.ltrim($relative, '/');

        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
