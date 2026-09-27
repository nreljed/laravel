<?php

namespace Nreljed\ShopModule\Tests\Concerns;

use Illuminate\Container\Container;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as AuthFactoryContract;
use Illuminate\Contracts\Auth\Guard as GuardContract;

/**
 * Binds a typed, "nobody is logged in" auth factory into a test container.
 *
 * `Product::creating()` stamps `created_by` from the current user via
 * `auth()->check()`. The `auth()` helper resolves the `Factory` *contract*
 * and then forwards unknown calls to the default guard, so both halves have
 * to exist for the helper's return type to hold.
 *
 * Any test that inserts a Product needs this; without it the boot hook throws
 * a container binding error rather than a useful assertion failure.
 */
trait ProvidesAnonymousAuth
{
    protected function bindAnonymousAuth(Container $container): void
    {
        $container->instance('auth', $this->anonymousAuthManager());
        $container->instance(AuthFactoryContract::class, $this->anonymousAuthManager());
    }

    private function anonymousAuthManager(): AuthFactoryContract
    {
        $guard = new class implements GuardContract
        {
            public function check(): bool
            {
                return false;
            }

            public function guest(): bool
            {
                return true;
            }

            public function user(): ?Authenticatable
            {
                return null;
            }

            public function id(): ?int
            {
                return null;
            }

            public function validate(array $credentials = []): bool
            {
                return false;
            }

            public function hasUser(): bool
            {
                return false;
            }

            public function setUser(Authenticatable $user): void {}
        };

        return new class($guard) implements AuthFactoryContract
        {
            public function __construct(private readonly GuardContract $guard) {}

            public function guard($name = null): GuardContract
            {
                return $this->guard;
            }

            public function shouldUse($name): void {}

            public function __call(string $method, array $parameters): mixed
            {
                return $this->guard->{$method}(...$parameters);
            }
        };
    }
}
