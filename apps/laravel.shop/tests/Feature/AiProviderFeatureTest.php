<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Exceptions\RateLimitedException;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Nreljed\Platform\Ai\Exceptions\MissingAiProviderException;
use Nreljed\Platform\Ai\Models\AiProvider;
use Nreljed\Platform\Ai\Models\AiUsageSetting;
use Nreljed\Platform\Ai\Services\AiHttpClient;
use Nreljed\Platform\Ai\Services\AiService;
use Nreljed\Platform\Ai\Services\AiTaskRunner;
use Tests\TestCase;

class AiProviderFeatureTest extends TestCase
{
    use MockeryPHPUnitIntegration;
    use RefreshDatabase;

    protected function user(): User
    {
        return User::factory()->create();
    }

    protected function providerData(?string $provider = 'gemini', ?string $key = 'sk-test-gemini-1234567890'): array
    {
        return [
            'provider' => $provider,
            'api_key' => $key,
            'preferred_model' => 'gemini-3.6-flash',
        ];
    }

    public function test_user_can_store_an_ai_provider_key(): void
    {
        $user = $this->user();

        $response = $this->actingAs($user)
            ->postJson('/dashboard/account/ai-keys', $this->providerData());

        $response->assertOk()
            ->assertJsonPath('provider.provider', 'gemini')
            ->assertJsonPath('provider.is_active', true)
            ->assertJsonPath('provider.preferred_model', 'gemini-3.6-flash')
            ->assertJsonPath('provider.masked_key', 'sk-t...7890')
            ->assertJsonMissingPath('provider.api_key');

        $this->assertDatabaseHas('ai_providers', [
            'user_id' => $user->id,
            'provider' => 'gemini',
        ]);

        $provider = $user->aiProviders()->first();
        $this->assertSame('sk-test-gemini-1234567890', $provider->getDecryptedKey());
    }

    public function test_storing_same_provider_updates_instead_of_duplicating(): void
    {
        $user = $this->user();

        $this->actingAs($user)->postJson('/dashboard/account/ai-keys', $this->providerData());
        $this->actingAs($user)->postJson('/dashboard/account/ai-keys', $this->providerData(key: 'sk-test-gemini-0987654321'));

        $this->assertSame(1, $user->aiProviders()->count());
        $this->assertSame('sk-test-gemini-0987654321', $user->aiProviders()->first()->getDecryptedKey());
    }

    public function test_store_validates_provider_and_key(): void
    {
        $user = $this->user();

        $this->actingAs($user)->postJson('/dashboard/account/ai-keys', $this->providerData(provider: 'not-a-provider'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('provider');

        $this->actingAs($user)->postJson('/dashboard/account/ai-keys', $this->providerData(key: 'short'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('api_key');
    }

    public function test_user_can_list_only_their_own_keys(): void
    {
        $user = $this->user();
        $other = $this->user();

        $this->actingAs($user)->postJson('/dashboard/account/ai-keys', $this->providerData());
        $this->actingAs($other)->postJson('/dashboard/account/ai-keys', $this->providerData(provider: 'openai', key: 'sk-test-openai-1234567890'));

        $this->actingAs($user)->getJson('/dashboard/account/ai-keys')
            ->assertOk()
            ->assertJsonCount(1, 'providers')
            ->assertJsonPath('providers.0.provider', 'gemini')
            ->assertJsonPath('providers.0.masked_key', 'sk-t...7890');
    }

    public function test_user_can_update_provider_settings(): void
    {
        $user = $this->user();
        $provider = $this->createProvider($user, 'gemini');

        $this->actingAs($user)->putJson("/dashboard/account/ai-keys/{$provider->id}", [
            'preferred_model' => 'gemini-3.5-flash-lite',
            'is_active' => false,
        ])->assertOk()
            ->assertJsonPath('provider.preferred_model', 'gemini-3.5-flash-lite')
            ->assertJsonPath('provider.is_active', false);

        $this->assertFalse($provider->fresh()->is_active);
    }

    public function test_user_cannot_update_or_delete_another_users_provider(): void
    {
        $owner = $this->user();
        $intruder = $this->user();
        $provider = $this->createProvider($owner, 'gemini');

        $this->actingAs($intruder)->putJson("/dashboard/account/ai-keys/{$provider->id}", ['is_active' => false])
            ->assertForbidden();

        $this->actingAs($intruder)->deleteJson("/dashboard/account/ai-keys/{$provider->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('ai_providers', ['id' => $provider->id]);
    }

    public function test_user_can_delete_their_provider_key(): void
    {
        $user = $this->user();
        $provider = $this->createProvider($user, 'gemini');

        $this->actingAs($user)->deleteJson("/dashboard/account/ai-keys/{$provider->id}")
            ->assertOk()
            ->assertJsonPath('message', 'AI provider key removed.');

        $this->assertDatabaseMissing('ai_providers', ['id' => $provider->id]);
    }

    public function test_guest_is_redirected_from_ai_keys_routes(): void
    {
        $this->get('/dashboard/account/ai-keys')->assertRedirect(route('login'));
        $this->postJson('/dashboard/account/ai-keys', $this->providerData())->assertUnauthorized();
    }

    public function test_usage_snapshot_rolls_up_caps_today_and_all_time(): void
    {
        $user = $this->user();
        $service = app(AiService::class);

        $service->logUsage(
            user: $user,
            agentClass: 'Tests\\Feature', provider: 'gemini', model: 'gemini-3.6-flash',
            inputTokens: 5, outputTokens: 3, latencyMs: 120,
        );

        $snapshot = $this->actingAs($user)->getJson('/dashboard/account/ai-keys/usage')
            ->assertOk()
            ->assertJsonPath('usage.cap.requests', AiUsageSetting::DEFAULT_CAP_REQUESTS)
            ->assertJsonPath('usage.cap.tokens', AiUsageSetting::DEFAULT_CAP_TOKENS)
            ->assertJsonPath('usage.today.totals.requests', 1)
            ->assertJsonPath('usage.today.totals.tokens', 8)
            ->assertJsonPath('usage.all_time.totals.requests', 1)
            ->assertJsonPath('usage.all_time.totals.tokens', 8)
            ->assertJsonPath('usage.today.providers.gemini.requests', 1)
            ->assertJsonPath('usage.today.providers.gemini.last_latency_ms', 120)
            ->assertJsonPath('usage.today.providers.gemini.last_error', null);

        $successRow = $user->aiUsageLogs()->whereNull('error')->first();
        $this->assertSame(120, $successRow->latency_ms);

        $service->recordError($user, 'gemini', 'boom');

        $this->actingAs($user)->getJson('/dashboard/account/ai-keys/usage')
            ->assertOk()
            ->assertJsonPath('usage.today.providers.gemini.last_latency_ms', null)
            ->assertJsonPath('usage.today.providers.gemini.last_error', 'boom');
    }

    public function test_usage_caps_can_be_updated_and_clamped(): void
    {
        $user = $this->user();

        $this->actingAs($user)->putJson('/dashboard/account/ai-keys/usage-cap', [
            'requests' => 42,
            'tokens' => 5000,
        ])->assertOk()
            ->assertJsonPath('cap.requests', 42)
            ->assertJsonPath('cap.tokens', 5000);

        $this->assertDatabaseHas('ai_usage_settings', [
            'user_id' => $user->id,
            'cap_requests' => 42,
            'cap_tokens' => 5000,
        ]);

        $this->actingAs($user)->putJson('/dashboard/account/ai-keys/usage-cap', [
            'requests' => 99999999999,
            'tokens' => 999999999999,
        ])->assertOk()
            ->assertJsonPath('cap.requests', AiUsageSetting::CAP_REQUESTS_MAX)
            ->assertJsonPath('cap.tokens', AiUsageSetting::CAP_TOKENS_MAX);
    }

    public function test_usage_caps_validation(): void
    {
        $this->actingAs($this->user())->putJson('/dashboard/account/ai-keys/usage-cap', [
            'requests' => 0,
            'tokens' => 500,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['requests', 'tokens']);
    }

    public function test_ai_provider_mass_assignment_encrypts_api_key_via_mutator(): void
    {
        $provider = AiProvider::create([
            'user_id' => $this->user()->id,
            'provider' => 'openai',
            'api_key' => 'sk-test-openai-1234567890',
            'preferred_model' => null,
            'is_active' => true,
        ]);

        $this->assertSame('sk-test-openai-1234567890', $provider->getDecryptedKey());
        $this->assertNotSame('sk-test-openai-1234567890', $provider->getAttributes()['api_key_encrypted']);
        $this->assertSame('sk-t...7890', $provider->getMaskedKey());
    }

    public function test_ai_service_resolves_active_provider_and_applies_default_model(): void
    {
        $user = $this->user();
        $service = app(AiService::class);

        $this->expectException(MissingAiProviderException::class);
        $service->resolveForUser($user);

        $this->createProvider($user, 'gemini');

        $resolved = app(AiService::class)->resolveForUser($user);
        $this->assertSame('gemini', $resolved['provider']);
        $this->assertSame('sk-test-gemini-1234567890', $resolved['key']);
        $this->assertSame(AiHttpClient::getDefaultModel('gemini'), $resolved['model']);
    }

    public function test_ai_service_detects_breached_caps(): void
    {
        $user = $this->user();
        $service = app(AiService::class);

        $service->updateCaps($user, 2, 1000);
        $service->logUsage(user: $user, agentClass: 'Tests', provider: 'gemini', model: 'm', inputTokens: 400, outputTokens: 0);

        $this->assertNull($service->exceedsCaps($user, 100, 0));

        $service->logUsage(user: $user, agentClass: 'Tests', provider: 'gemini', model: 'm', inputTokens: 400, outputTokens: 0);
        $this->assertTrue($service->exceedsCaps($user, 0, 0)['requests']);

        $exceeded = $service->exceedsCaps($user, 300, 0);
        $this->assertSame(['requests' => true, 'tokens' => true], $exceeded);
    }

    public function test_ai_task_runner_prompts_and_logs_usage(): void
    {
        $user = $this->user();
        $this->createProvider($user, 'gemini');

        $response = new AgentResponse('inv-1', 'ok', new Usage(promptTokens: 5, completionTokens: 3), new Meta);
        $agent = Mockery::mock(Agent::class);
        $agent->shouldReceive('prompt')->once()->andReturn($response);

        $runner = new AiTaskRunner(app(AiService::class));
        $result = $runner->prompt($agent, $user, 'hello', timeout: 30);

        $this->assertSame('ok', (string) $result);
        $this->assertSame(1, $user->aiUsageLogs()->count());
        $log = $user->aiUsageLogs()->first();
        $this->assertSame(5, $log->input_tokens);
        $this->assertSame(3, $log->output_tokens);
        $this->assertNotNull($log->latency_ms);
        $this->assertNull($log->error);
    }

    public function test_ai_task_runner_retries_rate_limits_then_succeeds(): void
    {
        $user = $this->user();
        $this->createProvider($user, 'gemini');

        $response = new AgentResponse('inv-2', 'retried', new Usage(promptTokens: 1, completionTokens: 1), new Meta);
        $agent = Mockery::mock(Agent::class);
        $attempts = 0;
        $agent->shouldReceive('prompt')->andReturnUsing(function () use (&$attempts, $response) {
            $attempts++;
            if ($attempts < 3) {
                throw RateLimitedException::forProvider('gemini');
            }

            return $response;
        });

        $runner = new AiTaskRunner(app(AiService::class));
        $result = $runner->prompt($agent, $user, 'hello', timeout: 30);

        $this->assertSame('retried', (string) $result);
        $this->assertSame(3, $attempts);
        $this->assertSame(1, $user->aiUsageLogs()->count());
    }

    public function test_ai_task_runner_blocks_when_caps_exceeded(): void
    {
        $user = $this->user();
        $this->createProvider($user, 'gemini');
        $service = app(AiService::class);
        $service->updateCaps($user, 1, 1000);
        $service->logUsage(user: $user, agentClass: 'Tests', provider: 'gemini', model: 'm', inputTokens: 1, outputTokens: 0);

        $agent = Mockery::mock(Agent::class);
        $agent->shouldReceive('prompt')->never();

        $runner = new AiTaskRunner($service);

        try {
            $runner->prompt($agent, $user, 'hello');
            $this->fail('Expected RuntimeException for breached caps.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('daily request limit', $e->getMessage());
        }
    }

    public function test_connection_test_endpoint_reports_failure_on_non_2xx(): void
    {
        Http::fake(['*' => Http::response('nope', 500)]);

        $this->actingAs($this->user())
            ->postJson('/dashboard/account/ai-keys/test', $this->providerData())
            ->assertUnprocessable()
            ->assertJsonPath('success', false);

        Http::assertSentCount(1);
    }

    private function createProvider(User $user, string $provider): AiProvider
    {
        return AiProvider::create([
            'user_id' => $user->id,
            'provider' => $provider,
            'api_key' => 'sk-test-gemini-1234567890',
            'preferred_model' => 'gemini-3.6-flash',
            'is_active' => true,
        ]);
    }
}
