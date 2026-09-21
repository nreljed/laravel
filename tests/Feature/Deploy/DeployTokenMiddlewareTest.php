<?php

namespace Tests\Feature\Deploy;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Tests\TestCase;

class DeployTokenMiddlewareTest extends TestCase
{
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->token = 'test-deploy-token-'.Str::random(16);
        Config::set('deploy.token', $this->token);
    }

    public function test_every_deploy_route_has_the_deploy_token_middleware(): void
    {
        $deployRoutes = collect(app('router')->getRoutes())
            ->filter(fn ($route) => str_starts_with($route->uri(), 'deploy/'));

        $this->assertGreaterThan(0, $deployRoutes->count());

        foreach ($deployRoutes as $route) {
            $this->assertContains(
                'deploy-token',
                $route->middleware(),
                "Deploy route '{$route->uri()}' is missing the 'deploy-token' middleware."
            );
        }
    }

    public function test_deploy_env_rejects_request_without_token_before_touching_env(): void
    {
        $response = $this->postJson('/deploy/env', ['values' => ['APP_NAME' => 'hacked']]);

        $response->assertStatus(403);
    }

    public function test_deploy_accepts_bearer_token_header(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/deploy/create-admin', ['password' => 'short']);

        $response->assertStatus(400);
        $response->assertJson(['error' => 'Password must be at least 8 characters']);
    }

    public function test_authorization_bearer_does_not_leak_when_token_present_in_body(): void
    {
        // Bearer-first contract: a stale Bearer header is rejected even when
        // the body carries the correct token, so `?token=`/body fallback can
        // never bypass a broken header (v0.1.57 deploy-token hardening).
        $response = $this->withToken('stale-bearer-token')
            ->postJson('/deploy/create-admin', [
                'token' => $this->token,
                'password' => 'short',
            ]);

        $response->assertStatus(403);
    }
}
