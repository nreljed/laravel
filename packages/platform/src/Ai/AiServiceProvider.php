<?php

declare(strict_types=1);

namespace Nreljed\Platform\Ai;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Nreljed\Platform\Ai\Models\AiProvider;
use Nreljed\Platform\Ai\Policies\AiProviderPolicy;
use Nreljed\Platform\Concerns\InteractsWithFeatures;
use Nreljed\Platform\Contracts\FeatureProvider;

/**
 * AI feature module — bring-your-own-key provider management (Account → AI Keys).
 *
 * Routes and the AI provider policy are self-contained here so the kernel
 * provider stays feature-agnostic.
 */
class AiServiceProvider extends ServiceProvider implements FeatureProvider
{
    use InteractsWithFeatures;

    public function name(): string
    {
        return 'AI';
    }

    public function slug(): string
    {
        return 'ai';
    }

    public function description(): ?string
    {
        return 'Bring-your-own-key AI providers, preferences, and usage tracking.';
    }

    public function boot(): void
    {
        Route::middleware(['web', 'auth', 'maintenance'])
            ->prefix('/dashboard/account')
            ->name('dashboard.account.ai-keys.')
            ->group(__DIR__.'/Routes/web.php');

        Gate::policy(AiProvider::class, AiProviderPolicy::class);
    }
}
