import { Head, Link, usePage } from "@inertiajs/react";
import PublicLayout from "@/Layouts/PublicLayout";
import { Badge } from "@/Components/ui/badge";
import ContentBlockRenderer from "@/Components/blog/ContentBlockRenderer";

interface Author {
  id: number;
  name: string;
}

interface Category {
  id: number;
  name: string;
  slug: string;
}

interface Tag {
  id: number;
  name: string;
  slug: string;
}

interface ContentBlock {
  id?: number;
  type: string;
  content: string | null;
  media_url: string | null;
  media_alt: string | null;
  settings: Record<string, unknown> | null;
  order: number;
}

interface Post {
  id: number;
  title: string;
  slug: string;
  content: string | null;
  excerpt: string | null;
  featured_image: string | null;
  published_at: string | null;
  meta_title: string | null;
  meta_description: string | null;
  og_image: string | null;
  user: Author;
  category: Category | null;
  tags: Tag[];
  content_blocks: ContentBlock[];
}

interface BlogShowProps {
  post: Post;
}

export default function BlogShow({ post }: BlogShowProps) {
  const pageProps = usePage().props as Record<string, unknown>;
  const appUrl = (pageProps.app_url as string) || "";
  const canonical = appUrl ? `${appUrl}/blog/${post.slug}` : undefined;
  const ogImageUrl = post.og_image || post.featured_image || undefined;

  const jsonLd = {
    "@context": "https://schema.org",
    "@type": "BlogPosting",
    headline: post.title,
    description: post.meta_description || post.excerpt || undefined,
    image: ogImageUrl,
    url: canonical,
    datePublished: post.published_at || undefined,
    author: post.user ? { "@type": "Person", name: post.user.name } : undefined,
    publisher: appUrl
      ? { "@type": "Organization", name: "Cost Breaker 360", url: appUrl }
      : undefined,
    mainEntityOfPage: canonical ? { "@type": "WebPage", "@id": canonical } : undefined,
    keywords: post.tags?.map((t) => t.name).join(", ") || undefined,
    articleSection: post.category?.name || undefined,
  };

  return (
    <PublicLayout
      title={post.meta_title || post.title}
      description={post.meta_description || post.excerpt || undefined}
      ogImage={ogImageUrl}
      ogType="article"
      canonical={canonical}
      articlePublishedTime={post.published_at || undefined}
      articleAuthor={post.user?.name}
      articleSection={post.category?.name || undefined}
      articleTags={post.tags?.map((t) => t.name)}
      twitterCard="summary_large_image"
    >
      <Head>
        <script
          type="application/ld+json"
          dangerouslySetInnerHTML={{ __html: JSON.stringify(jsonLd) }}
        />
      </Head>

      <article className="mx-auto max-w-3xl px-4 py-16 sm:px-6 lg:px-8">
        <header className="mb-8">
          {post.category && (
            <Link
              href={route("blog.index", { category: post.category.slug })}
              className="mb-4 inline-block text-sm font-medium uppercase tracking-wider text-primary hover:text-primary/80"
            >
              {post.category.name}
            </Link>
          )}
          <h1 className="text-4xl font-bold tracking-tight text-foreground sm:text-5xl">
            {post.title}
          </h1>
          <div className="mt-4 flex items-center gap-3 text-sm text-muted-foreground">
            <span>{post.user.name}</span>
            {post.published_at && (
              <>
                <span>&middot;</span>
                <time>
                  {new Date(post.published_at).toLocaleDateString("en-US", {
                    year: "numeric",
                    month: "long",
                    day: "numeric",
                  })}
                </time>
              </>
            )}
          </div>
          {post.tags.length > 0 && (
            <div className="mt-4 flex flex-wrap gap-2">
              {post.tags.map((tag) => (
                <Link key={tag.id} href={route("blog.index", { tag: tag.slug })}>
                  <Badge variant="secondary" className="text-xs">
                    {tag.name}
                  </Badge>
                </Link>
              ))}
            </div>
          )}
        </header>

        {post.featured_image && (
          <div className="mb-8 overflow-hidden rounded-lg">
            <img
              src={post.featured_image}
              alt={post.title}
              className="h-auto w-full object-cover"
            />
          </div>
        )}

        {post.excerpt && (
          <p className="mb-8 text-lg leading-relaxed text-muted-foreground">{post.excerpt}</p>
        )}

        {post.content && (
          <div
            className="prose prose-neutral dark:prose-invert max-w-none mb-8"
            dangerouslySetInnerHTML={{ __html: post.content }}
          />
        )}

        {post.content_blocks.length > 0 && <ContentBlockRenderer blocks={post.content_blocks} />}

        <div className="mt-12 border-t border-border pt-8">
          <Link
            href={route("blog.index")}
            className="text-sm font-medium text-primary hover:text-primary/80"
          >
            &larr; Back to all posts
          </Link>
        </div>
      </article>
    </PublicLayout>
  );
}
