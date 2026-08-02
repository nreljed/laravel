<?php

namespace Nreljed\DashboardCore\Ai\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Ai\Ai;
use Laravel\Ai\Exceptions\AiException;
use Nreljed\DashboardCore\Ai\Models\AiProvider;
use Nreljed\DashboardCore\Ai\Requests\StoreAiProviderRequest;
use Nreljed\DashboardCore\Ai\Requests\TestAiProviderRequest;
use Nreljed\DashboardCore\Http\Controllers\Controller;

use function Laravel\Ai\agent;

class AiProviderController extends Controller
{
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
        $providerName = $request->validated('provider');
        $apiKey = $request->validated('api_key');

        $tempInstance = 'test_'.Str::random(16);
        config(["ai.providers.{$tempInstance}" => array_filter([
            'driver' => $providerName,
            'key' => $apiKey,
            'model' => $request->validated('preferred_model'),
        ])]);

        try {
            $response = agent('You are a test assistant. Respond with exactly one word: ok')
                ->prompt('Respond with ok', provider: $tempInstance);

            return response()->json([
                'success' => true,
                'message' => 'Connection verified successfully.',
                'response' => substr((string) $response, 0, 100),
            ]);
        } catch (AiException $e) {
            Log::warning('AI provider test failed', [
                'provider' => $providerName,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Connection failed: '.$e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            Log::warning('AI provider test failed', [
                'provider' => $providerName,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Connection failed. Please check your API key.',
            ], 422);
        } finally {
            config(["ai.providers.{$tempInstance}" => null]);
        }
    }
}
