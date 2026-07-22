<?php

namespace Database\Seeders;

use App\Blog\Models\Post;
use App\Blog\Models\PostCategory;
use App\Blog\Models\PostTag;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BlogContentSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Technology', 'slug' => 'technology', 'description' => 'Posts about technology and software development.', 'order' => 1],
            ['name' => 'Design', 'slug' => 'design', 'description' => 'Posts about UI/UX design, branding, and visual storytelling.', 'order' => 2],
            ['name' => 'Business', 'slug' => 'business', 'description' => 'Posts about strategy, growth, and operations.', 'order' => 3],
            ['name' => 'Tutorials', 'slug' => 'tutorials', 'description' => 'Step-by-step guides and walkthroughs.', 'order' => 4],
            ['name' => 'News', 'slug' => 'news', 'description' => 'Latest updates and announcements.', 'order' => 5],
        ];

        $createdCategories = [];
        foreach ($categories as $cat) {
            $createdCategories[] = PostCategory::firstOrCreate(
                ['slug' => $cat['slug']],
                $cat,
            );
        }

        $tagNames = ['Laravel', 'React', 'TypeScript', 'Tailwind CSS', 'Lexical', 'Inertia.js', 'Shadcn UI', 'PHP', 'JavaScript', 'Performance'];
        $createdTags = [];
        foreach ($tagNames as $name) {
            $createdTags[] = PostTag::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name],
            );
        }

        $admin = User::where('email', 'nstudio.webdesign@gmail.com')->first()
            ?? User::where('has_role', 'admin')->first()
            ?? User::first();

        if (! $admin) {
            return;
        }

        // Delete existing demo posts to allow re-seeding
        Post::whereIn('slug', [
            'editor-showcase-all-features-demo',
            'draft-post-coming-soon-features',
        ])->delete();

        $demoContent = <<<'HTML'
<h1>Lexical Editor Feature Showcase</h1>

<p>This post demonstrates every feature of the Lexical rich text editor integrated into Cost Breaker 360. Each section highlights a different capability, from basic formatting to advanced elements like tables and embedded media.</p>

<h2>Inline Text Formatting</h2>

<p>The toolbar provides five inline formatting options. Here is each one in action:</p>

<ul>
  <li><strong>Bold text</strong> — use for key terms and important calls to action</li>
  <li><em>Italic text</em> — use for emphasis, titles, and foreign words</li>
  <li><u>Underlined text</u> — use sparingly for special emphasis</li>
  <li><s>Strikethrough text</s> — use to indicate deleted or outdated content</li>
  <li><code>Inline code</code> — use for function names, variables, and technical terms</li>
</ul>

<p>You can also <strong><em>combine bold and italic</em></strong> for maximum emphasis, or use <strong><u>bold with underline</u></strong> for highlighted terms. These combinations help create clear visual hierarchy within your paragraphs.</p>

<h2>Headings</h2>

<p>The editor supports three heading levels for structuring your content. This post uses <strong>H1</strong> for the main title, <strong>H2</strong> for major sections, and <strong>H3</strong> for subsections. Proper heading hierarchy improves both readability and SEO.</p>

<h3>Example Subsection</h3>

<p>This is an H3 heading, useful for breaking up content within a larger section without creating a full new section break.</p>

<h2>Blockquotes</h2>

<blockquote>Good design is as little design as possible. Less, but better — because it concentrates on the essential aspects, and the products are not burdened with non-essentials. Back to purity, back to simplicity. — Dieter Rams</blockquote>

<p>Blockquotes are perfect for highlighting testimonials, pulling key insights from longer passages, or referencing external sources. The styling includes a left border and muted text color to visually separate quoted content.</p>

<h2>Code Blocks</h2>

<p>For longer code samples, use the code block format. The editor uses <a href="https://prismjs.com">Prism.js</a> for syntax highlighting:</p>

<pre><code class="language-php">&lt;?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

class MediaService
{
    const int MAX_DIMENSION = 1920;
    const int QUALITY = 80;

    public function upload(UploadedFile $file, string $folder): array
    {
        $mime = $file->getMimeType();

        if (in_array($mime, self::VIDEO_MIMES)) {
            return $this->storeVideo($file, $folder);
        }

        return $this->processImage($file, $folder);
    }
}</code></pre>

<p>Code blocks preserve formatting, use monospace fonts, and include syntax highlighting for supported languages. The dark background ensures readability against the page.</p>

<h2>Lists</h2>

<h3>Bullet List</h3>

<p>Unordered lists are ideal for items where sequence does not matter:</p>

<ul>
  <li>Lexical editor with plugin architecture</li>
  <li>Shadcn/ui component library for consistent design</li>
  <li>Inertia.js for SPA-like navigation</li>
  <li>Laravel backend with Eloquent ORM</li>
  <li>Vite for fast asset bundling</li>
</ul>

<h3>Numbered List</h3>

<p>Ordered lists work best for step-by-step instructions or ranked items:</p>

<ol>
  <li>Install Laravel and configure your environment</li>
  <li>Set up Inertia.js with React as the frontend adapter</li>
  <li>Install and configure the Lexical editor packages</li>
  <li>Build custom toolbar controls with Shadcn/ui components</li>
  <li>Deploy to production with zero-downtime</li>
</ol>

<h3>Checklist</h3>

<p>Checklists are great for tracking progress on tasks:</p>

<ul class="m-0 p-0 list-outside list-disc [&>li]:mt-2 ml-6 relative !list-disc">
  <li role="checkbox" tabindex="-1" aria-checked="true" value="1" class="list-none before:content-['☑'] before:mr-2 before:text-muted-foreground"><span data-lexical-text="true">Lexical editor setup with all plugins</span></li>
  <li role="checkbox" tabindex="-1" aria-checked="true" value="2" class="list-none before:content-['☑'] before:mr-2 before:text-muted-foreground"><span data-lexical-text="true">Image upload with MediaService processing</span></li>
  <li role="checkbox" tabindex="-1" aria-checked="true" value="3" class="list-none before:content-['☑'] before:mr-2 before:text-muted-foreground"><span data-lexical-text="true">Floating link editor for inline links</span></li>
  <li role="checkbox" tabindex="-1" aria-checked="true" value="4" class="list-none before:content-['☑'] before:mr-2 before:text-muted-foreground"><span data-lexical-text="true">Table insertion and manipulation</span></li>
  <li role="checkbox" tabindex="-1" aria-checked="false" value="5" class="list-none before:content-['☐'] before:mr-2 before:text-muted-foreground"><span data-lexical-text="true">Write comprehensive documentation</span></li>
  <li role="checkbox" tabindex="-1" aria-checked="false" value="6" class="list-none before:content-['☐'] before:mr-2 before:text-muted-foreground"><span data-lexical-text="true">Add end-to-end tests</span></li>
</ul>

<h2>Tables</h2>

<p>Tables are inserted as 3x3 grids by default and can be expanded with the toolbar controls:</p>

<table>
  <tr>
    <th>Feature</th>
    <th>Editor</th>
    <th>Public View</th>
    <th>Status</th>
  </tr>
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
    <td>Prism highlighting</td>
    <td>Dark theme</td>
    <td>Complete</td>
  </tr>
  <tr>
    <td>Links</td>
    <td>Floating editor</td>
    <td>Blue underline</td>
    <td>Complete</td>
  </tr>
</table>

<h2>Links and References</h2>

<p>The editor includes a <a href="https://lexical.dev">floating link editor</a> that appears when you select text and click the link button. You can <a href="https://laravel.com">add links to external resources</a>, <a href="https://react.dev">reference documentation</a>, or <a href="https://tailwindcss.com">link to design systems</a>.</p>

<p>Each link in this paragraph demonstrates the floating editor in action. The link color is a distinct blue that stands out from regular text, with a subtle underline that thickens on hover.</p>

<h2>Images</h2>

<p>Images can be inserted via the toolbar button, pasted from clipboard, or drag-and-dropped into the editor. All images are automatically processed through the MediaService, which resizes them to a maximum of 1920px and converts them to WebP format.</p>

<img src="https://placehold.co/800x400/1a1a2e/e0e0e0?text=Editor+Image+Upload+Demo" alt="Editor image upload demonstration" />

<p>The image above shows how uploaded images render within the editor. The MediaService handles optimization automatically, ensuring fast page loads without manual intervention.</p>

<h2>Mixed Formatting Examples</h2>

<p>Here are some real-world examples of mixed formatting:</p>

<ul>
  <li>The <strong><code>MediaService</code></strong> class handles all file uploads and processing</li>
  <li>Use <em><strong>italic bold</strong></em> for critical warnings in documentation</li>
  <li>The <s>$oldMethod()</s> has been replaced by <code>newMethod()</code></li>
  <li>Install via: <code>composer require laravel/ai</code></li>
</ul>

<h2>Conclusion</h2>

<p>This showcase demonstrates that the Lexical editor integration is <strong>complete and production-ready</strong>. Every toolbar feature — from basic text formatting to complex tables — works correctly both in the editor and on the public-facing blog.</p>

<p>The combination of <a href="https://lexical.dev">Lexical</a> for content editing, <a href="https://ui.shadcn.com">Shadcn/ui</a> for design consistency, and <a href="https://laravel.com">Laravel</a> for backend reliability creates a powerful content management experience.</p>
HTML;

        $post = Post::firstOrCreate(
            ['slug' => 'editor-showcase-all-features-demo'],
            [
                'user_id' => $admin->id,
                'title' => 'Lexical Editor Feature Showcase (Demo)',
                'content' => $demoContent,
                'excerpt' => 'A comprehensive demonstration of every Lexical editor feature: bold, italic, links, lists, tables, code blocks, images, blockquotes, and more.',
                'status' => 'published',
                'published_at' => now()->subDays(3),
                'category_id' => $createdCategories[0]->id,
                'meta_title' => 'Lexical Editor Feature Showcase | Cost Breaker 360',
                'meta_description' => 'See every Lexical editor feature in action: formatting, links, lists, tables, code blocks, and images.',
            ],
        );

        $post->tags()->syncWithoutDetaching(collect($createdTags)->pluck('id')->slice(0, 5)->toArray());

        $draftContent = <<<'HTML'
<h1>Draft Post: Coming Soon Features</h1>

<p>This is a <strong>draft</strong> post that demonstrates the draft status workflow. It includes some initial content but is not yet ready for publication.</p>

<h2>Planned Features</h2>

<ul>
  <li>Real-time collaborative editing</li>
  <li>AI-powered content suggestions</li>
  <li>Advanced media gallery management</li>
  <li>Multi-language support</li>
</ul>

<p>Stay tuned for updates!</p>
HTML;

        Post::firstOrCreate(
            ['slug' => 'draft-post-coming-soon-features'],
            [
                'user_id' => $admin->id,
                'title' => 'Draft Post: Coming Soon Features',
                'content' => $draftContent,
                'excerpt' => 'A preview of upcoming features planned for the platform.',
                'status' => 'draft',
                'category_id' => $createdCategories[4]->id,
            ],
        );
    }
}
