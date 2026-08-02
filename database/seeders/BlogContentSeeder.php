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
    private const string SOURCE_IMAGES_DIR = 'D:\WEB PROJECTS\Archived projects\myhome\Source Demo Properties Images';

    private const array DEMO_IMAGES = [
        'sanibell-bv-530lZQXMKGw-unsplash.jpg',
        'sanibell-bv-w2Ey_vOI-H8-unsplash.jpg',
        'douglas-sheppard-CGZbE-Pa1S8-unsplash.jpg',
        'charkrit-t1LQYntvpCc-unsplash.jpg',
        'michiel-van-kaam-QhbtepGK5Yg-unsplash.jpg',
    ];

    public function run(): void
    {
        $this->copyDemoImages();

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

        $img = fn (string $file, string $alt, int $w = 800, int $h = 600): string => '<span class="block my-4 rounded-md max-w-full" style="position: relative; display: inline-block;">'
            .'<img src="/storage/posts/'.$file.'" alt="'.$alt.'" class="max-w-full rounded-md object-cover transition-shadow" width="'.$w.'" height="'.$h.'" />'
            .'</span>';

        $layout2 = fn (string $left, string $right): string => '<div style="grid-template-columns: 1fr 1fr;" data-lexical-layout-container="true">'
            .'<div data-lexical-layout-item="true">'.$left.'</div>'
            .'<div data-lexical-layout-item="true">'.$right.'</div>'
            .'</div>';

        $layout3 = fn (string $a, string $b, string $c): string => '<div style="grid-template-columns: 1fr 1fr 1fr;" data-lexical-layout-container="true">'
            .'<div data-lexical-layout-item="true">'.$a.'</div>'
            .'<div data-lexical-layout-item="true">'.$b.'</div>'
            .'<div data-lexical-layout-item="true">'.$c.'</div>'
            .'</div>';

        $featuredImageUrl = '/storage/posts/sanibell-bv-530lZQXMKGw-unsplash.jpg';

        $twoColContent = $layout2(
            '<h3>Design First</h3>'
            .'<p>Every project starts with understanding the user. Wireframes, prototypes, and design systems ensure consistency across every screen.</p>'
            .$img('sanibell-bv-w2Ey_vOI-H8-unsplash.jpg', 'Modern interior design showcase', 800, 600),
            '<h3>Built to Scale</h3>'
            .'<p>Robust architecture with Laravel, Inertia.js, and React delivers a seamless experience that grows with your business.</p>'
            .$img('douglas-sheppard-CGZbE-Pa1S8-unsplash.jpg', 'Modern building architecture', 800, 600),
        );

        $threeColContent = $layout3(
            '<h3>Speed</h3>'
            .'<p>Vite-powered builds with hot module replacement for instant feedback during development.</p>'
            .$img('charkrit-t1LQYntvpCc-unsplash.jpg', 'Fast modern workspace', 400, 300),
            '<h3>Security</h3>'
            .'<p>Sanctum authentication, CSRF protection, and role-based access control baked in from day one.</p>'
            .$img('michiel-van-kaam-QhbtepGK5Yg-unsplash.jpg', 'Secure modern home', 400, 300),
            '<h3>Polish</h3>'
            .'<p>Shadcn/ui components, dark mode, and responsive design create a professional experience.</p>'
            .$img('sanibell-bv-530lZQXMKGw-unsplash.jpg', 'Polished interior design', 400, 300),
        );

        $imagesDemoContent = $img('douglas-sheppard-CGZbE-Pa1S8-unsplash.jpg', 'Editor image upload demonstration', 800, 600);

        $demoContent = <<<'HTML'
<h1>Lexical Editor Feature Showcase</h1>

<p>This post demonstrates every feature of the Lexical rich text editor integrated into Cost Breaker 360. Each section highlights a different capability, from basic formatting to advanced elements like tables, column layouts, and embedded media.</p>

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

<h2>Column Layouts</h2>

<p>The editor supports multi-column layouts using CSS Grid. Columns can be resized by dragging the divider in the editor, and content flows naturally within each column.</p>

<h3>Two-Column Layout</h3>

<p>Two-column layouts are ideal for side-by-side comparisons or pairing text with images:</p>

<!--TWO_COL-->

<h3>Three-Column Layout</h3>

<p>Three columns work well for feature grids or showcasing multiple items:</p>

<!--THREE_COL-->

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
  <li role="checkbox" tabindex="-1" aria-checked="true" value="1" class="lexical-checklist-checked"><span data-lexical-text="true">Lexical editor setup with all plugins</span></li>
  <li role="checkbox" tabindex="-1" aria-checked="true" value="2" class="lexical-checklist-checked"><span data-lexical-text="true">Image upload with MediaService processing</span></li>
  <li role="checkbox" tabindex="-1" aria-checked="true" value="3" class="lexical-checklist-checked"><span data-lexical-text="true">Floating link editor for inline links</span></li>
  <li role="checkbox" tabindex="-1" aria-checked="true" value="4" class="lexical-checklist-checked"><span data-lexical-text="true">Table insertion and manipulation</span></li>
  <li role="checkbox" tabindex="-1" aria-checked="true" value="5" class="lexical-checklist-checked"><span data-lexical-text="true">Column layout support with CSS Grid</span></li>
  <li role="checkbox" tabindex="-1" aria-checked="false" value="6" class="lexical-checklist-unchecked"><span data-lexical-text="true">Write comprehensive documentation</span></li>
  <li role="checkbox" tabindex="-1" aria-checked="false" value="7" class="lexical-checklist-unchecked"><span data-lexical-text="true">Add end-to-end tests</span></li>
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
    <td>Column Layouts</td>
    <td>Grid-based columns</td>
    <td>Responsive grid</td>
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

<!--IMAGE_DEMO-->

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

<p>This showcase demonstrates that the Lexical editor integration is <strong>complete and production-ready</strong>. Every toolbar feature — from basic text formatting to complex tables and column layouts — works correctly both in the editor and on the public-facing blog.</p>

<p>The combination of <a href="https://lexical.dev">Lexical</a> for content editing, <a href="https://ui.shadcn.com">Shadcn/ui</a> for design consistency, and <a href="https://laravel.com">Laravel</a> for backend reliability creates a powerful content management experience.</p>
HTML;

        $demoContent = str_replace(
            ['<!--TWO_COL-->', '<!--THREE_COL-->', '<!--IMAGE_DEMO-->'],
            [$twoColContent, $threeColContent, $imagesDemoContent],
            $demoContent,
        );

        $post = Post::firstOrCreate(
            ['slug' => 'editor-showcase-all-features-demo'],
            [
                'user_id' => $admin->id,
                'title' => 'Lexical Editor Feature Showcase (Demo)',
                'content' => $demoContent,
                'excerpt' => 'A comprehensive demonstration of every Lexical editor feature: formatting, links, lists, tables, column layouts, images, and more.',
                'status' => 'published',
                'published_at' => now()->subDays(3),
                'category_id' => $createdCategories[0]->id,
                'featured_image' => $featuredImageUrl,
                'meta_title' => 'Lexical Editor Feature Showcase | Cost Breaker 360',
                'meta_description' => 'See every Lexical editor feature in action: formatting, links, lists, tables, column layouts, and images.',
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

    private function copyDemoImages(): void
    {
        $destDir = storage_path('app/public/posts');
        if (! is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }

        foreach (self::DEMO_IMAGES as $filename) {
            $dest = $destDir.'/'.$filename;
            if (file_exists($dest)) {
                continue;
            }
            $source = self::SOURCE_IMAGES_DIR.'\\'.$filename;
            if (file_exists($source)) {
                copy($source, $dest);
            }
        }
    }
}
