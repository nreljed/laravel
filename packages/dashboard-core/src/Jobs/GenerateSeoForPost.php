<?php

namespace Nreljed\DashboardCore\Jobs;

use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Nreljed\DashboardCore\Ai\Agents\SeoAgent;
use Nreljed\DashboardCore\Ai\Exceptions\MissingAiProviderException;
use Nreljed\DashboardCore\Blog\Models\Post;
use Nreljed\DashboardCore\Models\User;

class GenerateSeoForPost implements ShouldQueue
{
    use Batchable, Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        protected Post $post,
        protected int $userId,
    ) {}

    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $user = User::find($this->userId);

        if (! $user) {
            Log::warning("[GenerateSeoForPost] User {$this->userId} not found, skipping post {$this->post->id}");

            return;
        }

        try {
            $seo = app(SeoAgent::class)->generateSeo(
                user: $user,
                title: $this->post->title,
                content: $this->post->content ?? '',
            );

            $this->post->update([
                'meta_title' => $seo['title_options'][0] ?? null,
                'meta_description' => $seo['meta_description_options'][0] ?? null,
                'metadata' => array_merge($this->post->metadata ?? [], [
                    'seo' => $seo,
                    'seo_generated_at' => now()->toISOString(),
                ]),
            ]);
        } catch (MissingAiProviderException) {
            Log::warning("[GenerateSeoForPost] No AI provider for user {$this->userId}, skipping post {$this->post->id}");
        } catch (\Throwable $e) {
            Log::error("[GenerateSeoForPost] Failed for post {$this->post->id}: {$e->getMessage()}");
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("[GenerateSeoForPost] Job failed permanently for post {$this->post->id}: {$exception->getMessage()}");
    }
}
