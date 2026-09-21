<?php

namespace Tests\Feature\Ai;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Nreljed\Platform\Database\Seeders\RolesAndPermissionsSeeder;
use Tests\Concerns\CreatesTestUsers;
use Tests\TestCase;

class AiProviderTestConnectionTest extends TestCase
{
    use CreatesTestUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_test_connection_requires_authentication(): void
    {
        $response = $this->postJson(route('dashboard.account.ai-keys.test'), [
            'provider' => 'openai',
            'api_key' => 'sk-test-12345678',
        ]);

        $response->assertStatus(401);
    }

    public function test_test_connection_requires_valid_provider(): void
    {
        $user = $this->regularUser();

        $response = $this->actingAs($user)->postJson(route('dashboard.account.ai-keys.test'), [
            'provider' => '',
            'api_key' => 'sk-test-12345678',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('provider');
    }

    public function test_test_connection_requires_api_key(): void
    {
        $user = $this->regularUser();

        $response = $this->actingAs($user)->postJson(route('dashboard.account.ai-keys.test'), [
            'provider' => 'openai',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('api_key');
    }

    public function test_test_connection_rejects_invalid_provider(): void
    {
        $user = $this->regularUser();

        $response = $this->actingAs($user)->postJson(route('dashboard.account.ai-keys.test'), [
            'provider' => 'nonexistent_provider',
            'api_key' => 'sk-test-12345678',
        ]);

        $response->assertStatus(422);
    }
}
