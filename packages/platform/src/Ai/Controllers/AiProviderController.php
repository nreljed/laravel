<?php

namespace Nreljed\Platform\Ai\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Ai\Ai;
use Laravel\Ai\Exceptions\AiException;
use Nreljed\Platform\Ai\Models\AiProvider;
use Nreljed\Platform\Ai\Models\AiUsageSetting;
use Nreljed\Platform\Ai\Requests\StoreAiProviderRequest;
use Nreljed\Platform\Ai\Requests\TestAiProviderRequest;
use Nreljed\Platform\Ai\Services\AiService;
use Nreljed\Platform\Http\Controllers\Controller;

use function Laravel\Ai\agent;

class AiProviderController extends Controller
{
    public function __construct(
        protected AiService $aiService,
    ) {}

    /**
     * Return the authenticated user's AI usage snapshot (caps, today, all-time).
     */
    public function usage(Request $request): JsonResponse
    {
        return response()->json([
            'usage' => $this->aiService->getUsageSnapshot($request->user()),
        ]);
    }

    /**
     * Update the authenticated user's soft daily AI usage caps.
     */
    public function usageCap(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'requests' => ['required', 'integer', 'min:1'],
            'cost_cents' => ['required', 'integer', 'min:1', 'max:'.AiUsageSetting::CAP_COST_MAX],
        ]);

        $caps = $this->aiService->updateCaps(
            user: $request->user(),
            requests: $validated['requests'],
            costCents: $validated['cost_cents'],
        );

        if ($request->inertia()) {
            return redirect()->back();
        }

        return response()->json([
            'cap' => $caps,
            'message' => 'AI usage caps updated.',
        ]);
    }

    /**
     * List all AI provider keys for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $supported = AiProvider::supportedProviders();

        $providers = $request->user()
            ->aiProviders()
            ->get()
            ->map(fn ($provider) => [
                'id' => $provider->id,
                'provider' => $provider->provider,
                'provider_name' => isset($supported[$provider->provider]) ? $supported[$provider->provider]['name'] : $provider->provider,
                'masked_key' => $provider->getMaskedKey(),
                'preferred_model' => $provider->preferred_model,
                'is_active' => $provider->is_active,
                'created_at' => $provider->created_at?->toDateString(),
                'last_tested_at' => $provider->last_tested_at?->toDateTimeString(),
                'last_test_ok' => $provider->last_test_ok,
            ]);

        return response()->json(['providers' => $providers]);
    }

    /**
     * Store or update an AI provider key.
     */
    public function store(StoreAiProviderRequest $request): JsonResponse|RedirectResponse
    {
        $this->authorize('create', AiProvider::class);

        $user = $request->user();

        $existing = $user->aiProviders()
            ->where('provider', $request->validated('provider'))
            ->first();

        if ($existing) {
            $existing->update([
                'api_key_encrypted' => Crypt::encryptString(
                    $request->validated('api_key')
                ),
                'preferred_model' => $request->validated('preferred_model'),
                'is_active' => true,
            ]);

            $provider = $existing;
        } else {
            $provider = $user->aiProviders()->create([
                'provider' => $request->validated('provider'),
                'api_key_encrypted' => Crypt::encryptString(
                    $request->validated('api_key')
                ),
                'preferred_model' => $request->validated('preferred_model'),
                'is_active' => true,
            ]);
        }

        if ($request->inertia()) {
            return redirect()->back();
        }

        return response()->json([
            'provider' => [
                'id' => $provider->id,
                'provider' => $provider->provider,
                'masked_key' => $provider->getMaskedKey(),
                'preferred_model' => $provider->preferred_model,
                'is_active' => $provider->is_active,
            ],
            'message' => 'AI provider key saved successfully.',
        ]);
    }

    /**
     * Update an AI provider's settings.
     */
    public function update(Request $request, AiProvider $aiProvider): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $aiProvider);

        $validated = $request->validate([
            'preferred_model' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ]);

        $aiProvider->update($validated);

        if ($request->inertia()) {
            return redirect()->back();
        }

        return response()->json([
            'provider' => [
                'id' => $aiProvider->id,
                'provider' => $aiProvider->provider,
                'masked_key' => $aiProvider->getMaskedKey(),
                'preferred_model' => $aiProvider->preferred_model,
                'is_active' => $aiProvider->is_active,
            ],
            'message' => 'AI provider updated.',
        ]);
    }

    /**
     * Delete an AI provider key.
     */
    public function destroy(Request $request, AiProvider $aiProvider): JsonResponse|RedirectResponse
    {
        $this->authorize('delete', $aiProvider);

        $aiProvider->delete();

        if ($request->inertia()) {
            return redirect()->back();
        }

        return response()->json(['message' => 'AI provider key removed.']);
    }

    public function test(TestAiProviderRequest $request): JsonResponse
    {
        $result = $this->runConnectionTest(
            $request->validated('provider'),
            $request->validated('api_key'),
            $request->validated('preferred_model'),
        );

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    /**
     * Test a *stored* key for the authenticated user's provider entry.
     *
     * Decrypts the key server-side, exercises the same SDK driver stack the
     * agents use, and persists the outcome (`last_tested_at` / `last_test_ok`)
     * so the Integrations status pill reflects the latest run.
     */
    public function testStored(Request $request, AiProvider $aiProvider): JsonResponse
    {
        $this->authorize('update', $aiProvider);

        $config = AiProvider::supportedProviders()[$aiProvider->provider] ?? null;

        if (($config['testable'] ?? false) === false) {
            return response()->json([
                'success' => false,
                'message' => 'Connection testing is not available for this provider.',
            ], 422);
        }

        $result = $this->runConnectionTest(
            $aiProvider->provider,
            $aiProvider->getDecryptedKey(),
            $aiProvider->preferred_model ?? $config['default_model'] ?? null,
        );

        $aiProvider->update([
            'last_tested_at' => now(),
            'last_test_ok' => $result['success'],
        ]);

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    /**
     * Spin up a throwaway SDK provider instance and exercise it with a
     * one-word prompt. Shared by the pre-save `test()` and the stored-key
     * `testStored()` so both paths validate against the same driver stack
     * the agents actually use.
     *
     * @return array{success: bool, message: string, response?: string}
     */
    private function runConnectionTest(string $providerName, string $apiKey, ?string $model): array
    {
        $tempInstance = 'test_'.Str::random(16);
        config(["ai.providers.{$tempInstance}" => array_filter([
            'driver' => $providerName,
            'key' => $apiKey,
            'model' => $model,
        ])]);

        try {
            $response = agent('You are a test assistant. Respond with exactly one word: ok')
                ->prompt('Respond with ok', provider: $tempInstance);

            return [
                'success' => true,
                'message' => 'Connection verified successfully.',
                'response' => substr((string) $response, 0, 100),
            ];
        } catch (AiException $e) {
            Log::warning('AI provider test failed', [
                'provider' => $providerName,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Connection failed: '.$e->getMessage(),
            ];
        } catch (\Exception $e) {
            Log::warning('AI provider test failed', [
                'provider' => $providerName,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Connection failed. Please check your API key.',
            ];
        } finally {
            config(["ai.providers.{$tempInstance}" => null]);
        }
    }
}
