<?php

namespace Tests\Feature\Jobs;

use App\Ai\Agents\SeoAgent;
use App\Ai\Exceptions\MissingAiProviderException;
use App\Jobs\GenerateSeoForPost;
use Database\Factories\PostFactory;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTestUsers;
use Tests\TestCase;

class GenerateSeoForPostTest extends TestCase
{
    use CreatesTestUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_job_updates_post_with_generated_seo(): void
    {
        $user = $this->regularUser();
        $post = PostFactory::new()->create(['user_id' => $user->id]);

        $this->mock(SeoAgent::class, function ($mock) {
            $mock->shouldReceive('generateSeo')
                ->once()
                ->andReturn([
                    'title_options' => ['Title A', 'Title B'],
                    'meta_description_options' => ['Desc A', 'Desc B'],
                ]);
        });

        (new GenerateSeoForPost($post, $user->id))->handle();

        $post->refresh();
        $this->assertEquals('Title A', $post->meta_title);
        $this->assertEquals('Desc A', $post->meta_description);
        $this->assertArrayHasKey('seo', $post->metadata);
        $this->assertArrayHasKey('seo_generated_at', $post->metadata);
    }

    public function test_job_skips_when_user_does_not_exist(): void
    {
        $post = PostFactory::new()->create();

        (new GenerateSeoForPost($post, 999999))->handle();

        $this->assertNull($post->fresh()->meta_title);
        $this->assertNull($post->fresh()->meta_description);
    }

    public function test_job_swallows_missing_ai_provider_exception(): void
    {
        $user = $this->regularUser();
        $post = PostFactory::new()->create(['user_id' => $user->id]);

        $this->mock(SeoAgent::class, function ($mock) {
            $mock->shouldReceive('generateSeo')->once()->andThrow(new MissingAiProviderException);
        });

        (new GenerateSeoForPost($post, $user->id))->handle();

        $this->assertNull($post->fresh()->meta_title);
    }

    public function test_job_reraises_unexpected_exceptions(): void
    {
        $user = $this->regularUser();
        $post = PostFactory::new()->create(['user_id' => $user->id]);

        $this->mock(SeoAgent::class, function ($mock) {
            $mock->shouldReceive('generateSeo')->once()->andThrow(new \RuntimeException('provider boom'));
        });

        $this->expectException(\RuntimeException::class);

        (new GenerateSeoForPost($post, $user->id))->handle();
    }
}
