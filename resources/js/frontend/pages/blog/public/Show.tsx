import { Head, Link, usePage } from "@inertiajs/react";
import { useEffect } from "react";
import { Pencil } from "lucide-react";
import PublicLayout from "../../../layouts/PublicLayout";
import { SitePill } from "../../../components/site/SitePill";
import ProseContent from "@nreljed/platform/Components/blog/ProseContent";
import { useAuth } from "@nreljed/platform/hooks/use-auth";
import { highlightAllCodeBlocks } from "@nreljed/platform/lib/shiki-highlight";
import { demoteBodyHeadings } from "./demote-headings";

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
}

export default function BlogShow({ post }: BlogShowProps) {
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

      <article className="mx-auto w-full max-w-site px-site-lg py-site-4xl sm:px-site-2xl sm:py-site-5xl">
        <header className="relative sm:text-center">
          {canEdit && (
            <Link
              href={route("dashboard.posts.edit", post.id)}
              className="absolute right-0 top-0 inline-flex items-center gap-1.5 rounded-site-pill border border-site-border bg-site-surface px-2.5 py-1 text-xs font-medium text-site-ink-soft transition-colors hover:border-site-ink hover:text-site-ink"
            >
              <Pencil className="h-3 w-3" />
              Edit
            </Link>
          )}
          {post.category && (
            <Link
              href={route("blog.index", { category: post.category.slug })}
              className="mb-5 inline-flex items-center gap-2 text-xs font-bold tracking-[0.2em] text-site-secondary uppercase transition-colors hover:text-site-ink"
            >
              <span className="h-1.5 w-1.5 rounded-full bg-site-accent" />
              {post.category.name}
            </Link>
          )}
          <h1 className="site-display max-w-site-display text-balance sm:mx-auto">{post.title}</h1>
          <div className="mt-site-md flex items-center gap-3 text-sm text-site-ink-faint sm:justify-center">
            <span>{post.user.name}</span>
            {post.published_at && (
              <>
                <span>&middot;</span>
                <time dateTime={post.published_at}>
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
            <div className="mt-site-md flex flex-wrap gap-2 sm:justify-center">
              {post.tags.map((tag) => (
                <SitePill key={tag.id} href={route("blog.index", { tag: tag.slug })}>
                  {tag.name}
                </SitePill>
              ))}
            </div>
          )}
        </header>

        {post.featured_image && (
          <figure className="mt-site-3xl overflow-hidden rounded-site-card">
            <img
              src={post.featured_image}
              srcSet={post.featured_image_srcset || undefined}
              sizes="(min-width: 1280px) 1280px, 100vw"
              alt={post.title}
              loading="lazy"
              decoding="async"
              className="h-auto w-full object-cover"
            />
          </figure>
        )}

        <div className="mx-auto mt-site-3xl max-w-site-measure">
          {post.excerpt && (
            <p className="mb-site-xl text-lg leading-relaxed text-site-ink-soft">{post.excerpt}</p>
          )}

          {post.content && <ProseContent html={demoteBodyHeadings(post.content)} />}

          <div className="mt-site-3xl border-t border-site-border pt-site-xl">
            <Link
              href={route("blog.index")}
              className="text-sm font-medium text-site-secondary hover:text-site-ink"
            >
              &larr; Back to all posts
            </Link>
          </div>
        </div>
      </article>
    </PublicLayout>
  );
}
