<?php

namespace Tests\Feature\Ai;

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Nreljed\Platform\Ai\Models\AiProvider;
use Tests\Concerns\CreatesTestUsers;
use Tests\TestCase;

class AiProviderCrudTest extends TestCase
{
    use CreatesTestUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_user_can_list_own_providers(): void
    {
        $user = $this->regularUser();

        $response = $this->actingAs($user)->getJson(route('dashboard.account.ai-keys.index'));

        $response->assertOk();
        $response->assertJsonStructure(['providers']);
    }

    public function test_user_can_store_provider(): void
    {
        $user = $this->regularUser();

        $response = $this->actingAs($user)->postJson(route('dashboard.account.ai-keys.store'), [
            'provider' => 'openai',
            'api_key' => 'sk-test-12345678',
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'provider' => ['id', 'provider', 'masked_key', 'preferred_model', 'is_active'],
            'message',
        ]);

        $this->assertDatabaseHas('ai_providers', [
            'user_id' => $user->id,
            'provider' => 'openai',
            'is_active' => true,
        ]);

        $record = AiProvider::where('user_id', $user->id)->where('provider', 'openai')->first();
        $this->assertNotEquals('sk-test-12345678', $record->api_key_encrypted);
        $this->assertEquals('sk-test-12345678', $record->getDecryptedKey());
    }

    public function test_user_can_update_provider_preferred_model(): void
    {
        $user = $this->regularUser();
        $provider = $user->aiProviders()->create([
            'provider' => 'openai',
            'api_key_encrypted' => Crypt::encryptString('sk-test-12345678'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->putJson(
            route('dashboard.account.ai-keys.update', ['aiProvider' => $provider]),
            ['preferred_model' => 'gpt-4o']
        );

        $response->assertOk();
        $response->assertJsonPath('provider.preferred_model', 'gpt-4o');

        $this->assertDatabaseHas('ai_providers', [
            'id' => $provider->id,
            'preferred_model' => 'gpt-4o',
        ]);
    }

    public function test_user_can_delete_provider(): void
    {
        $user = $this->regularUser();
        $provider = $user->aiProviders()->create([
            'provider' => 'openai',
            'api_key_encrypted' => Crypt::encryptString('sk-test-12345678'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->deleteJson(
            route('dashboard.account.ai-keys.destroy', ['aiProvider' => $provider])
        );

        $response->assertOk();
        $this->assertDatabaseMissing('ai_providers', ['id' => $provider->id]);
    }

    public function test_user_cannot_access_other_users_providers(): void
    {
        $userA = $this->regularUser();
        $userB = $this->regularUser();

        $userA->aiProviders()->create([
            'provider' => 'openai',
            'api_key_encrypted' => Crypt::encryptString('sk-test-user-a-key'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($userB)->getJson(route('dashboard.account.ai-keys.index'));

        $response->assertOk();
        $response->assertJsonCount(0, 'providers');
    }

    public function test_store_upserts_existing_provider(): void
    {
        $user = $this->regularUser();

        $this->actingAs($user)->postJson(route('dashboard.account.ai-keys.store'), [
            'provider' => 'openai',
            'api_key' => 'sk-test-first-key-1234',
        ]);

        $this->actingAs($user)->postJson(route('dashboard.account.ai-keys.store'), [
            'provider' => 'openai',
            'api_key' => 'sk-test-second-key-5678',
        ]);

        $this->assertDatabaseCount('ai_providers', 1);
        $this->assertDatabaseHas('ai_providers', [
            'user_id' => $user->id,
            'provider' => 'openai',
        ]);

        $record = AiProvider::where('user_id', $user->id)->where('provider', 'openai')->first();
        $this->assertEquals('sk-test-second-key-5678', $record->getDecryptedKey());
    }
}
