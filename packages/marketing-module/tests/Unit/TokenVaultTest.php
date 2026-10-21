<?php

namespace Nreljed\MarketingModule\Tests\Unit;

use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Container\Container;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Facade;
use Nreljed\MarketingModule\Services\TokenVault;
use PHPUnit\Framework\TestCase;

final class TokenVaultTest extends TestCase
{
    private TokenVault $vault;

    protected function setUp(): void
    {
        parent::setUp();

        $container = new Container;
        Container::setInstance($container);
        Facade::setFacadeApplication($container);
        Facade::clearResolvedInstances();

        $container->instance('config', new ConfigRepository([]));
        $container->singleton('encrypter', fn () => new Encrypter(random_bytes(32), 'AES-256-CBC'));

        $this->vault = new TokenVault;
    }

    public function test_it_round_trips_a_token(): void
    {
        $encrypted = $this->vault->encrypt('super-secret-token');

        $this->assertNotNull($encrypted);
        $this->assertNotSame('super-secret-token', $encrypted);
        $this->assertSame('super-secret-token', $this->vault->decrypt($encrypted));
    }

    public function test_empty_values_stay_empty(): void
    {
        $this->assertNull($this->vault->encrypt(null));
        $this->assertNull($this->vault->encrypt(''));
        $this->assertNull($this->vault->decrypt(null));
        $this->assertNull($this->vault->decrypt(''));
    }

    public function test_an_undecryptable_token_returns_null_instead_of_throwing(): void
    {
        // A rotated APP_KEY or a row written by an older schema must not take
        // the whole account list down.
        $this->assertNull($this->vault->decrypt('not-actually-encrypted'));
    }

    public function test_it_masks_a_token(): void
    {
        $masked = $this->vault->mask('abcdefghijklmnop');

        $this->assertSame('********mnop', $masked);
    }

    public function test_it_masks_a_short_token_completely(): void
    {
        $this->assertSame('****', $this->vault->mask('abcd'));
    }

    public function test_it_masks_a_stored_token_without_exposing_it(): void
    {
        $encrypted = $this->vault->encrypt('abcdefghijklmnop');

        $masked = $this->vault->maskStored($encrypted);

        $this->assertSame('********mnop', $masked);
        $this->assertStringNotContainsString('abcdefgh', $masked);
    }
}
