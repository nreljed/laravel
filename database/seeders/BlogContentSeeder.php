<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Nreljed\DashboardCore\Blog\Models\Post;
use Nreljed\DashboardCore\Blog\Models\PostCategory;
use Nreljed\DashboardCore\Blog\Models\PostTag;
use Nreljed\DashboardCore\Models\User;

class BlogContentSeeder extends Seeder
{
    /**
     * @var array<string, int> slug => id for created categories
     */
    private array $categoryIds = [];

    public function run(): void
    {
        // Remove all existing posts so the demo feed is exactly what this seeder defines.
        Post::query()->delete();

        $this->seedCategories();
        $this->seedTags();

        $users = [
            'admin' => $this->resolveUser('nstudio'),
            'editor' => $this->resolveUser('editor'),
            'author' => $this->resolveUser('author'),
            'user' => $this->resolveUser('user'),
        ];

        $this->seedShowcase($users['admin']);
        $this->seedLaravelTutorial($users['author']);
        $this->seedAiAgents($users['editor']);
        $this->seedWelcome($users['user']);
    }

    private function seedCategories(): void
    {
        $defs = [
            ['slug' => 'technology', 'name' => 'Technology', 'description' => 'Posts about technology and software development.', 'order' => 1],
            ['slug' => 'web-development', 'name' => 'Web Development', 'description' => 'Building the web: frameworks, tooling, and frontend architecture.', 'order' => 2, 'parent' => 'technology'],
            ['slug' => 'laravel', 'name' => 'Laravel', 'description' => 'Laravel backend development tips and patterns.', 'order' => 3, 'parent' => 'web-development'],
            ['slug' => 'react', 'name' => 'React', 'description' => 'React and Inertia.js frontend development.', 'order' => 4, 'parent' => 'web-development'],
            ['slug' => 'artificial-intelligence', 'name' => 'Artificial Intelligence', 'description' => 'AI tooling, agents, and automation.', 'order' => 5, 'parent' => 'technology'],
            ['slug' => 'design', 'name' => 'Design', 'description' => 'Posts about UI/UX design, branding, and visual storytelling.', 'order' => 6],
            ['slug' => 'ui-ux', 'name' => 'UI/UX', 'description' => 'Interfaces, usability, and design systems.', 'order' => 7, 'parent' => 'design'],
            ['slug' => 'branding', 'name' => 'Branding', 'description' => 'Identity, messaging, and brand strategy.', 'order' => 8, 'parent' => 'design'],
            ['slug' => 'business', 'name' => 'Business', 'description' => 'Posts about strategy, growth, and operations.', 'order' => 9],
            ['slug' => 'tutorials', 'name' => 'Tutorials', 'description' => 'Step-by-step guides and walkthroughs.', 'order' => 10],
            ['slug' => 'backend', 'name' => 'Backend', 'description' => 'Server-side guides and API patterns.', 'order' => 11, 'parent' => 'tutorials'],
            ['slug' => 'news', 'name' => 'News', 'description' => 'Latest updates and announcements.', 'order' => 12],
        ];

        foreach ($defs as $def) {
            $parentId = isset($def['parent'])
                ? ($this->categoryIds[$def['parent']] ?? null)
                : null;

            $parentDepth = $parentId !== null
                ? PostCategory::find($parentId)?->depth ?? 0
                : 0;

            $depth = $parentId !== null ? $parentDepth + 1 : 0;

            $category = PostCategory::firstOrCreate(
                ['slug' => $def['slug']],
                [
                    'name' => $def['name'],
                    'parent_id' => $parentId,
                    'depth' => $depth,
                    'order' => $def['order'],
                    'description' => $def['description'] ?? null,
                ],
            );

            $category->update([
                'parent_id' => $parentId,
                'depth' => $depth,
                'name' => $def['name'],
                'description' => $def['description'] ?? null,
                'order' => $def['order'],
            ]);

            $this->categoryIds[$def['slug']] = $category->id;
        }
    }

    private function seedTags(): void
    {
        $tagNames = [
            'Laravel', 'React', 'TypeScript', 'Tailwind CSS', 'Lexical',
            'Inertia.js', 'Shadcn UI', 'PHP', 'JavaScript', 'Performance', 'SEO', 'Design Systems',
        ];

        foreach ($tagNames as $name) {
            PostTag::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name],
            );
        }
    }

    private function resolveUser(string $username): User
    {
        return User::where('username', $username)->first()
            ?? User::where('has_role', $username)->first()
            ?? User::first();
    }

    /**
     * Render a demo image pulled from the online Picsum placeholder service.
     * Images are deterministic per seed string, so reseeding always yields the
     * same photo and no image files are required in the repo or on disk.
     */
    private function img(string $seed, string $alt, int $w = 800, int $h = 600): string
    {
        $src = 'https://picsum.photos/seed/'.$seed.'/'.$w.'/'.$h;

        return '<span class="block my-4 rounded-md max-w-full" style="position: relative; display: inline-block;">'
            .'<img src="'.$src.'" alt="'.$alt.'" class="max-w-full rounded-md object-cover transition-shadow" width="'.$w.'" height="'.$h.'" loading="lazy" />'
            .'</span>';
    }

    /**
     * Featured / OG image URL for a post, served from the online Picsum service.
     */
    private function demoImage(string $seed): string
    {
        return 'https://picsum.photos/seed/'.$seed.'/1200/630';
    }

    private function codeBlock(string $language, string $code): string
    {
        $escaped = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');

        return '<pre data-language="'.$language.'" data-highlight-language="'.$language.'" data-theme="github-dark">'
            .'<code class="language-'.$language.'">'.$escaped.'</code></pre>';
    }

    private function layout2(string $left, string $right): string
    {
        return '<div style="grid-template-columns: 1fr 1fr;" data-lexical-layout-container="true" data-responsive-mode="full">'
            .'<div data-lexical-layout-item="true">'.$left.'</div>'
            .'<div data-lexical-layout-item="true">'.$right.'</div>'
            .'</div>';
    }

    private function layout3(string $a, string $b, string $c): string
    {
        return '<div style="grid-template-columns: 1fr 1fr 1fr;" data-lexical-layout-container="true" data-responsive-mode="full">'
            .'<div data-lexical-layout-item="true">'.$a.'</div>'
            .'<div data-lexical-layout-item="true">'.$b.'</div>'
            .'<div data-lexical-layout-item="true">'.$c.'</div>'
            .'</div>';
    }

    private function tags(string ...$names): array
    {
        return PostTag::whereIn('name', $names)->pluck('id')->all();
    }

    private function createPost(User $author, string $slug, array $data, array $tagNames): void
    {
        $post = Post::firstOrCreate(
            ['slug' => $slug],
            array_merge([
                'user_id' => $author->id,
                'status' => 'published',
            ], $data),
        );

        $post->tags()->sync($this->tags(...$tagNames));
    }

    private function seedShowcase(User $admin): void
    {
        $twoCol = $this->layout2(
            '<h3>Design First</h3>'
            .'<p>Every project starts with understanding the user. Wireframes, prototypes, and design systems ensure consistency across every screen.</p>'
            .$this->img('sanibell-bv-w2Ey_vOI-H8', 'Modern interior design showcase', 800, 600),
            '<h3>Built to Scale</h3>'
            .'<p>Robust architecture with Laravel, Inertia.js, and React delivers a seamless experience that grows with your business.</p>'
            .$this->img('douglas-sheppard-CGZbE-Pa1S8', 'Modern building architecture', 800, 600),
        );

        $threeCol = $this->layout3(
            '<h3>Speed</h3>'
            .'<p>Vite-powered builds with hot module replacement for instant feedback during development.</p>',
            '<h3>Security</h3>'
            .'<p>Sanctum authentication, CSRF protection, and role-based access control baked in from day one.</p>',
            '<h3>Polish</h3>'
            .'<p>Shadcn/ui components, dark mode, and responsive design create a professional experience.</p>',
        );

        $phpCode = <<<'CODE'
<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

class MediaService
{
    const int MAX_DIMENSION = 1920;
    const int QUALITY = 80;

    public function upload(UploadedFile $file, string $folder): array
    {
        return $this->processImage($file, $folder);
    }
}
CODE;

        $tsCode = <<<'CODE'
interface Post {
  id: number;
  title: string;
  content: string;
  published_at: string | null;
}

export function renderArticle(post: Post) {
  return (
    <article>
      <h1>{post.title}</h1>
      <ProseContent html={post.content} />
    </article>
  );
}
CODE;

        $content = <<<'HTML'
<h1>The Complete Lexical Editor Showcase</h1>

<p>This post demonstrates every feature of the Lexical rich text editor integrated into Cost Breaker 360. Each section highlights a different capability, from basic formatting to advanced elements like tables, column layouts, and embedded media.</p>

<h2>Inline Text Formatting</h2>

<p>The toolbar provides six inline formatting options. Here is each one in action:</p>

<ul>
  <li><strong>Bold text</strong> — use for key terms and important calls to action</li>
  <li><em>Italic text</em> — use for emphasis, titles, and foreign words</li>
  <li><u>Underlined text</u> — use sparingly for special emphasis</li>
  <li><s>Strikethrough text</s> — use to indicate deleted or outdated content</li>
  <li><code>Inline code</code> — use for function names, variables, and technical terms</li>
  <li><mark>Highlighted text</mark> — use to draw the reader's eye to a key phrase</li>
</ul>

<p>Advanced typography is also supported: H<sub>2</sub>O and E = mc<sup>2</sup> render correctly, and you can combine <strong><em>bold italic</em></strong> or <u><strong>underlined bold</strong></u> for layered emphasis.</p>

<h2>Headings Hierarchy</h2>

<p>The editor supports six heading levels. This is an H2, the previous one was H1. Here is how H3 through H6 render:</p>

<h3>This is an H3 heading</h3>

<p>H3 headings are great for subsections.</p>

<h4>This is an H4 heading</h4>

<h5>This is an H5 heading</h5>

<h6>This is an H6 heading</h6>

<h2>Links</h2>

<p>Inline links open in a new tab. Check the <a href="https://laravel.com" target="_blank" rel="noopener noreferrer">Laravel documentation</a>, browse the <a href="https://react.dev" target="_blank" rel="noopener noreferrer">React docs</a>, or read about <a href="https://inertiajs.com" target="_blank" rel="noopener noreferrer">Inertia.js</a>.</p>

<h2>Blockquotes</h2>

<blockquote>Good design is as little design as possible. Less, but better — because it concentrates on the essential aspects, and the products are not burdened with non-essentials. Back to purity, back to simplicity. — Dieter Rams</blockquote>

<h2>Lists</h2>

<h3>Bullet List</h3>

<ul>
  <li>Lexical editor with plugin architecture</li>
  <li>Shadcn/ui component library for consistent design</li>
  <li>Inertia.js for SPA-like navigation</li>
  <li>Laravel backend with Eloquent ORM</li>
  <li>Vite for fast asset bundling</li>
</ul>

<h3>Numbered List</h3>

<ol>
  <li>Install Laravel and configure your environment</li>
  <li>Set up Inertia.js with React as the frontend adapter</li>
  <li>Install and configure the Lexical editor packages</li>
  <li>Build custom toolbar controls with Shadcn/ui components</li>
  <li>Deploy to production with zero-downtime</li>
</ol>

<h3>Nested List</h3>

<ul>
  <li>Backend
    <ul>
      <li>Routing and middleware</li>
      <li>Eloquent models and relations</li>
      <li>Form validation</li>
    </ul>
  </li>
  <li>Frontend
    <ul>
      <li>React components
        <ul>
          <li>Server components</li>
          <li>Client components</li>
        </ul>
      </li>
      <li>Inertia pages</li>
    </ul>
  </li>
  <li>DevOps</li>
</ul>

<h3>Checklist</h3>

<p>Interactive checklists track progress on tasks:</p>

<ul data-is-checklist="1">
  <li role="checkbox" tabindex="-1" aria-checked="true" value="1" class="lexical-checklist-checked">Lexical editor setup with all plugins</li>
  <li role="checkbox" tabindex="-1" aria-checked="true" value="2" class="lexical-checklist-checked">Image upload with MediaService processing</li>
  <li role="checkbox" tabindex="-1" aria-checked="true" value="3" class="lexical-checklist-checked">Floating link editor for inline links</li>
  <li role="checkbox" tabindex="-1" aria-checked="false" value="4" class="lexical-checklist-unchecked">Write comprehensive documentation</li>
  <li role="checkbox" tabindex="-1" aria-checked="false" value="5" class="lexical-checklist-unchecked">Add end-to-end tests</li>
</ul>

<h2>Column Layouts</h2>

<p>The editor supports multi-column layouts using CSS Grid. Columns can be resized by dragging the divider in the editor, and content flows naturally within each column.</p>

<h3>Two-Column Layout</h3>

<p>Two-column layouts are ideal for side-by-side comparisons or pairing text with images:</p>

<!--TWO_COL-->

<h3>Three-Column Layout</h3>

<p>Three columns work well for feature grids or showcasing multiple items:</p>

<!--THREE_COL-->

<h2>Code Blocks</h2>

<p>Code blocks preserve formatting, use monospace fonts, include line numbers, and support syntax highlighting for multiple languages:</p>

<h3>PHP</h3>

<!--PHP_CODE-->

<h3>TypeScript / React</h3>

<!--TS_CODE-->

<h2>Tables</h2>

<p>Tables are inserted as 3x3 grids by default and can be expanded with the toolbar controls:</p>

<table>
  <thead>
    <tr>
      <th scope="col">Feature</th>
      <th scope="col">Editor</th>
      <th scope="col">Public View</th>
      <th scope="col">Status</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td>Rich Text</td>
      <td>Fully supported</td>
      <td>Renders with prose</td>
      <td>Complete</td>
    </tr>
    <tr>
      <td>Images</td>
      <td>Upload + URL</td>
      <td>Responsive display</td>
      <td>Complete</td>
    </tr>
    <tr>
      <td>Tables</td>
      <td>Insert + resize</td>
      <td>Bordered layout</td>
      <td>Complete</td>
    </tr>
    <tr>
      <td>Code Blocks</td>
      <td>Syntax highlighting</td>
      <td>Line-number gutter</td>
      <td>Complete</td>
    </tr>
    <tr>
      <td>Column Layouts</td>
      <td>Grid-based columns</td>
      <td>Responsive grid</td>
      <td>Complete</td>
    </tr>
  </tbody>
</table>

<h2>Images</h2>

<p>Images can be inserted via the toolbar button, pasted from clipboard, or drag-and-dropped into the editor. All images are automatically processed through the MediaService, which resizes them to a maximum of 1920px and converts them to WebP format.</p>

<!--IMAGE_DEMO-->

<h2>Horizontal Rules</h2>

<p>Use a horizontal rule to visually separate distinct sections of content without creating a new heading:</p>

<hr>

<p>This paragraph sits below the rule, cleanly separated from the section above it.</p>

<h2>Conclusion</h2>

<p>This showcase demonstrates that the Lexical editor integration is <strong>complete and production-ready</strong>. Every toolbar feature — from basic text formatting to complex tables and column layouts — works correctly both in the editor and on the public-facing blog.</p>
HTML;

        $content = str_replace(
            ['<!--TWO_COL-->', '<!--THREE_COL-->', '<!--IMAGE_DEMO-->', '<!--PHP_CODE-->', '<!--TS_CODE-->'],
            [
                $twoCol,
                $threeCol,
                $this->img('douglas-sheppard-CGZbE-Pa1S8', 'Editor image upload demonstration', 800, 600),
                $this->codeBlock('php', $phpCode),
                $this->codeBlock('ts', $tsCode),
            ],
            $content,
        );

        $this->createPost($admin, 'the-complete-lexical-editor-showcase', [
            'title' => 'The Complete Lexical Editor Showcase',
            'content' => $content,
            'excerpt' => 'A comprehensive demonstration of every Lexical editor feature: formatting, links, lists, tables, column layouts, code blocks, and images.',
            'published_at' => now()->subDays(3),
            'category_id' => $this->categoryIds['web-development'],
            'featured_image' => $this->demoImage('lexical-editor-showcase'),
            'meta_title' => 'The Complete Lexical Editor Showcase | Cost Breaker 360',
            'meta_description' => 'See every Lexical editor feature in action: formatting, links, lists, tables, column layouts, code blocks, and images.',
            'focus_keyword' => 'lexical editor features',
            'og_image' => $this->demoImage('lexical-editor-showcase'),
        ], ['Lexical', 'React', 'TypeScript', 'Tailwind CSS', 'Shadcn UI']);
    }

    private function seedLaravelTutorial(User $author): void
    {
        $modelCode = <<<'CODE'
<?php

namespace Nreljed\DashboardCore\Blog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Post extends Model
{
    protected $fillable = [
        'user_id', 'title', 'slug', 'content', 'status', 'category_id',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(PostCategory::class, 'category_id');
    }
}
CODE;

        $routeCode = <<<'CODE'
<?php

use Illuminate\Support\Facades\Route;
use Nreljed\DashboardCore\Blog\Controllers\PostController;

Route::middleware(['auth', 'verified'])
    ->prefix('dashboard/posts')
    ->name('dashboard.posts.')
    ->group(function () {
        Route::get('/', [PostController::class, 'index'])->name('index');
        Route::get('/create', [PostController::class, 'create'])->name('create');
        Route::post('/', [PostController::class, 'store'])->name('store');
    });
CODE;

        $content = <<<'HTML_WRAP'
        <h1>Building a Modern Blog with Laravel 13 and React 19</h1>
        
        <p>In this tutorial you will learn how to scaffold a feature-based blog exactly like the one powering this site — from routing and models to the Lexical editor.</p>
        
        <blockquote>This guide assumes you have Laravel 13, PHP 8.4, and Node 22 installed. The full source for this blog lives in <code>packages/dashboard-core/src/Blog</code>.</blockquote>
        
        <h2>What You Will Build</h2>
        
        <ul>
          <li>A self-contained <code>Blog</code> feature with its own routes, controllers, and models</li>
          <li>Nested category hierarchies with depth tracking</li>
          <li>A rich text editor powered by Lexical</li>
          <li>Public-facing pages rendered with Inertia.js</li>
        </ul>
        
        <h2>Step 1: The Post Model</h2>
        
        <p>Every feature keeps its own models under <code>src/{Feature}/Models</code>. Here is the <code>Post</code> model:</p>
        
        <!--MODEL_CODE-->
        
        <h2>Step 2: Feature Routes</h2>
        
        <p>Routes are registered by the feature's service provider, keeping the main <code>routes/web.php</code> free of feature noise:</p>
        
        <!--ROUTE_CODE-->
        
        <h2>Step 3: Inserting a Remote Image</h2>
        
        <p>Images can be referenced by URL without uploading. The editor preserves remote sources as-is:</p>
        
        <!--REMOTE_IMAGE-->
        
        <h2>Step 4: Rendering with Prose</h2>
        
        <p>On the public page, the stored HTML is rendered inside a <code>.prose</code> container so headings, lists, and tables inherit the blog typography. Column layouts keep their grid behavior thanks to the <code>data-lexical-layout-container</code> marker.</p>
        
        <table>
          <thead>
            <tr>
              <th scope="col">Concern</th>
              <th scope="col">Where it lives</th>
              <th scope="col">Why</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>Models</td>
              <td>src/Blog/Models</td>
              <td>Keeps feature entities self-contained</td>
            </tr>
            <tr>
              <td>Controllers</td>
              <td>src/Blog/Controllers</td>
              <td>HTTP glue specific to the feature</td>
            </tr>
            <tr>
              <td>Routes</td>
              <td>src/Blog/Routes</td>
              <td>Registered by BlogServiceProvider</td>
            </tr>
            <tr>
              <td>Pages</td>
              <td>src/Blog/Resources/js/Pages</td>
              <td>Inertia + React, colocated with the feature</td>
            </tr>
          </tbody>
        </table>
        
        <h2>Summary</h2>
        
        <p>You now have a complete, feature-based blog. The pattern scales: each new feature follows the same shape — <strong>Models, Controllers, Routes, and Resources</strong> — and is registered exactly once in the feature registry.</p>
        
        <ol>
          <li>Create the feature folder</li>
          <li>Add a service provider</li>
          <li>Register routes and permissions</li>
          <li>Build the Inertia pages</li>
          <li>Wire it into the feature registry</li>
        </ol>
        HTML_WRAP;

        $content = str_replace(
            ['<!--MODEL_CODE-->', '<!--ROUTE_CODE-->', '<!--REMOTE_IMAGE-->'],
            [
                $this->codeBlock('php', $modelCode),
                $this->codeBlock('php', $routeCode),
                $this->img('blog-laravel-tutorial', 'Remote image from Picsum', 800, 600),
            ],
            $content,
        );

        $this->createPost($author, 'building-a-modern-blog-with-laravel-13-and-react-19', [
            'title' => 'Building a Modern Blog with Laravel 13 and React 19',
            'content' => $content,
            'excerpt' => 'A step-by-step tutorial: feature-based architecture, nested categories, and the Lexical editor.',
            'published_at' => now()->subDays(1),
            'category_id' => $this->categoryIds['backend'],
            'featured_image' => $this->demoImage('laravel-blog-tutorial'),
            'meta_title' => 'Modern Blog with Laravel 13 and React 19 | Cost Breaker 360',
            'meta_description' => 'Learn to build a feature-based blog with Laravel 13, React 19, and the Lexical editor.',
            'focus_keyword' => 'laravel blog tutorial',
            'og_image' => $this->demoImage('laravel-blog-tutorial'),
        ], ['Laravel', 'React', 'PHP', 'TypeScript']);
    }

    private function seedAiAgents(User $editor): void
    {
        $agentCode = <<<'CODE'
<?php

namespace Nreljed\DashboardCore\Ai\Agents;

use Laravel\Ai\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\JsonSchema;
use Laravel\Ai\Traits\Promptable;

class SeoAgent implements Agent, HasStructuredOutput
{
    use Promptable, ResolvesUserProvider;

    public function provider(): string
    {
        return $this->getResolvedProvider()['driver'];
    }

    public function model(): ?string
    {
        return $this->getResolvedProvider()['model'];
    }

    public function schema(JsonSchema $schema): array
    {
        return $schema->object([
            'title' => $schema->string('SEO title'),
            'description' => $schema->string('Meta description'),
        ]);
    }
}
CODE;

        $content = <<<'HTML'
<h1>Laravel AI: Bring Your Own Key Agents</h1>

<p>Cost Breaker 360 runs a pure <strong>Bring Your Own Key</strong> model — the platform stores no API credentials and incurs zero AI cost. Users connect their own provider keys and the app resolves the correct provider per request.</p>

<h2>Supported Providers</h2>

<p>The agent system supports six cloud providers plus a local option:</p>

<table>
  <thead>
    <tr>
      <th scope="col">Provider</th>
      <th scope="col">Models</th>
      <th scope="col">Use case</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td>OpenAI</td>
      <td>gpt-4o, gpt-4.1, o3-mini</td>
      <td>General generation</td>
    </tr>
    <tr>
      <td>Anthropic</td>
      <td>claude-opus-4, claude-sonnet-4</td>
      <td>Long-form reasoning</td>
    </tr>
    <tr>
      <td>Google Gemini</td>
      <td>gemini-2.5-pro, gemini-2.5-flash</td>
      <td>Cost-efficient batch work</td>
    </tr>
    <tr>
      <td>Mistral</td>
      <td>mistral-large-latest</td>
      <td>European hosting</td>
    </tr>
    <tr>
      <td>DeepSeek</td>
      <td>deepseek-chat, deepseek-reasoner</td>
      <td>Open-source models</td>
    </tr>
    <tr>
      <td>xAI (Grok)</td>
      <td>grok-3, grok-3-mini</td>
      <td>Real-time world knowledge</td>
    </tr>
    <tr>
      <td>Ollama (Local)</td>
      <td>configurable</td>
      <td>Fully offline</td>
    </tr>
  </tbody>
</table>

<h2>How Provider Resolution Works</h2>

<p>Agent classes implement the <code>Agent</code> interface and use the <code>provider()</code> method for dynamic per-user key resolution. The method returns the provider's <strong>config key</strong> (a string); the per-user key is injected into that provider's config via <code>config()->set()</code> before prompting (see <code>ResolvesUserProvider::injectProviderKey()</code>):</p>

<!--AGENT_CODE-->

<h2>Security Checklist</h2>

<p>Key handling follows a strict checklist:</p>

<ul data-is-checklist="1">
  <li role="checkbox" tabindex="-1" aria-checked="true" value="1" class="lexical-checklist-checked">Keys encrypted at rest via <code>Crypt::encryptString()</code></li>
  <li role="checkbox" tabindex="-1" aria-checked="true" value="2" class="lexical-checklist-checked">Owner-only access via <code>AiProviderPolicy</code></li>
  <li role="checkbox" tabindex="-1" aria-checked="true" value="3" class="lexical-checklist-checked">Decryption happens on demand, never logged</li>
  <li role="checkbox" tabindex="-1" aria-checked="false" value="4" class="lexical-checklist-unchecked">Usage logging is best-effort and non-blocking</li>
</ul>

<h2>Agents Built In</h2>

<ol>
  <li><strong>SeoAgent</strong> — generates title and meta description with structured output</li>
  <li><strong>ChatAgent</strong> — streams dashboard answers over SSE</li>
  <li><strong>ContentAgent</strong> — drafts full articles from a brief</li>
</ol>

<blockquote>The key rule: the SDK's <code>provider()</code> returns a <em>config key</em> (string or failover array), not inline credentials. Per-user BYOK keys must be injected via <code>config()->set("ai.providers.{driver}.key", $key)</code> before prompting — safe per-request in PHP.</blockquote>
HTML;

        $content = str_replace('<!--AGENT_CODE-->', $this->codeBlock('php', $agentCode), $content);

        $this->createPost($editor, 'laravel-ai-bring-your-own-key-agents', [
            'title' => 'Laravel AI: Bring Your Own Key Agents',
            'content' => $content,
            'excerpt' => 'How Cost Breaker 360 runs zero-cost AI agents by resolving per-user provider keys dynamically.',
            'published_at' => now()->subDays(2),
            'category_id' => $this->categoryIds['artificial-intelligence'],
            'featured_image' => $this->demoImage('ai-agents'),
            'meta_title' => 'Laravel AI: Bring Your Own Key Agents | Cost Breaker 360',
            'meta_description' => 'Zero-cost AI agents in Laravel: BYOK provider resolution, encrypted key storage, and structured output.',
            'focus_keyword' => 'laravel ai agents',
            'og_image' => $this->demoImage('ai-agents'),
        ], ['Laravel', 'AI', 'PHP', 'TypeScript']);
    }

    private function seedWelcome(User $user): void
    {
        $content = <<<'HTML'
<h1>Welcome to the Cost Breaker 360 Blog</h1>

<p>Hello and welcome! This blog is where we share updates, guides, and behind-the-scenes stories about building Cost Breaker 360.</p>

<h2>What to Expect</h2>

<ul>
  <li>Release notes and product announcements</li>
  <li>Practical tutorials for developers</li>
  <li>Design and user experience deep dives</li>
</ul>

<h2>Getting Started</h2>

<p>Browse the categories to find content that interests you, or use the search box to jump straight to a topic. Every post is rendered with the same rich text experience you get in the editor.</p>

<blockquote>Happy reading — and if something is missing, tell us. The roadmap is shaped by your feedback.</blockquote>

<p>Happy reading!</p>
HTML;

        $this->createPost($user, 'welcome-to-the-cost-breaker-blog', [
            'title' => 'Welcome to the Cost Breaker 360 Blog',
            'content' => $content,
            'excerpt' => 'A warm welcome and a quick guide to what you will find on the blog.',
            'published_at' => now()->subHours(6),
            'category_id' => $this->categoryIds['business'],
            'featured_image' => $this->demoImage('welcome-blog'),
            'meta_title' => 'Welcome to the Blog | Cost Breaker 360',
            'meta_description' => 'Welcome to the Cost Breaker 360 blog — updates, tutorials, and product stories.',
            'focus_keyword' => 'welcome blog',
            'og_image' => $this->demoImage('welcome-blog'),
        ], ['JavaScript', 'Design Systems']);
    }
}
