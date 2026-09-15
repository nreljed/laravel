import { Head, Link, usePage } from "@inertiajs/react";
import { useEffect } from "react";
import { Eye, Pencil } from "lucide-react";
import PublicLayout from "@nreljed/platform/Layouts/PublicLayout";
import { Badge } from "@nreljed/platform/Components/ui/badge";
import ProseContent from "@nreljed/platform/Components/blog/ProseContent";
import { useAuth } from "@nreljed/platform/hooks/use-auth";
import { highlightAllCodeBlocks } from "@nreljed/platform/lib/shiki-highlight";

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

interface Post {
  id: number;
  title: string;
  slug: string;
  content: string | null;
  excerpt: string | null;
  featured_image: string | null;
  featured_image_srcset?: string | null;
  published_at: string | null;
  meta_title: string | null;
  meta_description: string | null;
  og_image: string | null;
  user: Author;
  category: Category | null;
  tags: Tag[];
}

interface BlogShowProps {
  post: Post;
  preview?: boolean;
}

export default function BlogShow({ post, preview = false }: BlogShowProps) {
  const pageProps = usePage().props as Record<string, unknown>;
  const appUrl = (pageProps.app_url as string) || "";
  const cspNonce = (pageProps.csp_nonce as string) || undefined;
  const canonical = appUrl ? `${appUrl}/blog/${post.slug}` : undefined;
  const ogImageUrl = post.og_image || post.featured_image || undefined;
  const { user, capabilities } = useAuth();
  const canEdit = user && (user.id === post.user.id || capabilities.can["posts.edit"]);

  useEffect(() => {
    highlightAllCodeBlocks();
  }, [post.content]);

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
      ? {
          "@type": "Organization",
          name: (pageProps.brand as { name?: string } | undefined)?.name || appUrl,
          url: appUrl,
        }
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
          nonce={cspNonce}
          dangerouslySetInnerHTML={{ __html: JSON.stringify(jsonLd) }}
        />
      </Head>

      <article className="mx-auto max-w-3xl px-4 py-16 sm:px-6 lg:px-8">
        {preview && (
          <div className="mb-8 flex items-center justify-between gap-2 rounded-lg border border-amber-300/60 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            <span className="flex items-center gap-2 font-medium">
              <Eye className="h-4 w-4" />
              Draft preview — this post is not publicly visible yet.
            </span>
            <Link
              href={route("dashboard.posts.edit", post.id)}
              className="inline-flex items-center gap-1.5 rounded-md border border-amber-300 bg-background px-2.5 py-1 text-xs font-medium text-foreground shadow-sm transition-colors hover:bg-amber-100"
            >
              <Pencil className="h-3 w-3" />
              Edit
            </Link>
          </div>
        )}

        <header className="relative mb-8">
          {!preview && canEdit && (
            <Link
              href={route("dashboard.posts.edit", post.id)}
              className="absolute right-0 top-0 inline-flex items-center gap-1.5 rounded-md border border-border bg-background px-2.5 py-1 text-xs font-medium text-muted-foreground shadow-sm transition-colors hover:bg-accent hover:text-accent-foreground"
            >
              <Pencil className="h-3 w-3" />
              Edit
            </Link>
          )}
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
              srcSet={post.featured_image_srcset || undefined}
              sizes="(min-width: 768px) 768px, 100vw"
              alt={post.title}
              loading="lazy"
              decoding="async"
              className="h-auto w-full object-cover"
            />
          </div>
        )}

        {post.excerpt && (
          <p className="mb-8 text-lg leading-relaxed text-muted-foreground">{post.excerpt}</p>
        )}

        {post.content && <ProseContent html={post.content} />}

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
