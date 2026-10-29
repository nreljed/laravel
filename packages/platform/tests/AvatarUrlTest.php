<?php

namespace Nreljed\Platform\Tests;

use Illuminate\Config\Repository;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;
use Nreljed\Platform\Models\User;
use PHPUnit\Framework\TestCase;

/**
 * Guards the avatar URL contract.
 *
 * `users.avatar` can name a file that no longer exists (seeded demo accounts,
 * manually removed uploads). The accessor used to return a URL for any
 * non-empty filename, so every page listing those users fired one 404 per row
 * (`/storage/avatars/<missing>.jpg`). A dangling reference must resolve to
 * null so the UI falls back to the user's initials.
 */
final class AvatarUrlTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir().'/platform-avatars-'.bin2hex(random_bytes(4));
        mkdir($this->root.'/avatars', 0777, true);

        $app = new Application(dirname(__DIR__));

        $app->instance('config', new Repository([
            'filesystems' => [
                'default' => 'local',
                'disks' => [
                    'avatars' => [
                        'driver' => 'local',
                        'root' => $this->root.'/avatars',
                        'url' => 'https://shop.test/storage/avatars',
                        'visibility' => 'public',
                        'throw' => false,
                    ],
                ],
            ],
        ]));

        $app->singleton('filesystem', static fn (Application $app): FilesystemManager => new FilesystemManager($app));
        $app->instance('request', Request::create('https://shop.test/dashboard'));
        $app->instance('app', $app);

        Facade::setFacadeApplication($app);
    }

    protected function tearDown(): void
    {
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);

        foreach (glob($this->root.'/avatars/*') ?: [] as $file) {
            @unlink($file);
        }

        @rmdir($this->root.'/avatars');
        @rmdir($this->root);

        parent::tearDown();
    }

    public function test_avatar_url_is_returned_for_an_existing_file(): void
    {
        touch($this->root.'/avatars/present.jpg');

        $user = (new User)->setRawAttributes(['avatar' => 'present.jpg'], true);

        $this->assertTrue($user->hasAvatarFile());
        $this->assertSame('https://shop.test/storage/avatars/present.jpg', $user->avatar_url);
    }

    public function test_avatar_url_is_null_when_the_file_is_missing(): void
    {
        $user = (new User)->setRawAttributes(['avatar' => 'deleted.jpg'], true);

        $this->assertFalse($user->hasAvatarFile());
        $this->assertNull($user->avatar_url);
    }

    public function test_avatar_url_is_null_without_an_avatar(): void
    {
        $user = (new User)->setRawAttributes(['avatar' => null], true);

        $this->assertFalse($user->hasAvatarFile());
        $this->assertNull($user->avatar_url);
    }
}
