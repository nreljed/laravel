import { useRef } from "react";
import { Head, Link, usePage } from "@inertiajs/react";
import PublicLayout from "../../../layouts/PublicLayout";
import { SitePill } from "../../../components/site/SitePill";
import { SitePagination } from "../../../components/site/SitePagination";
import { useReveal } from "../../../components/site";

interface Author {
  id: number;
  name: string;
}

interface Category {
  id: number;
  name: string;
  slug: string;
}

interface Post {
  id: number;
  title: string;
  slug: string;
  excerpt: string | null;
  featured_image: string | null;
  featured_image_srcset?: string | null;
  published_at: string | null;
  user: Author;
  category: Category | null;
}

interface PaginatedPosts {
  data: Post[];
  links: { url: string | null; label: string; active: boolean }[];
  path: string;
  current_page: number;
  prev_page_url: string | null;
  next_page_url: string | null;
}

interface ArchiveInfo {
  name: string;
  slug: string;
  meta_title?: string | null;
  meta_description?: string | null;
}

interface BlogIndexProps {
  posts: PaginatedPosts;
  category: ArchiveInfo | null;
  tag: ArchiveInfo | null;
}

export default function BlogIndex({ posts, category, tag }: BlogIndexProps) {
  const pageProps = usePage().props as Record<string, unknown>;
  const appUrl = (pageProps.app_url as string) || "";
  const rootRef = useRef<HTMLDivElement>(null);
  useReveal(rootRef, "[data-hero]", { group: true });
  useReveal(rootRef);

  const title = category?.meta_title
    ? category.meta_title
    : category
      ? `Category: ${category.name}`
      : tag
        ? `Tag: ${tag.name}`
        : "Blog";

  const description =
    category?.meta_description ||
    (tag ? `Posts tagged with ${tag.name}.` : "Read our latest articles and insights.");

  const heading = category ? category.name : tag ? tag.name : "Blog";
  const lead = category
    ? category.meta_description || "Articles in this category."
    : tag
      ? `Posts tagged with ${tag.name}.`
      : "Read our latest articles and insights.";

  const query = new URLSearchParams();
  if (category?.slug) query.set("category", category.slug);
  else if (tag?.slug) query.set("tag", tag.slug);
  if (posts.current_page > 1) query.set("page", String(posts.current_page));
  const queryString = query.toString();
  const canonical = appUrl
    ? `${appUrl}${posts.path}${queryString ? `?${queryString}` : ""}`
    : undefined;

  return (
    <PublicLayout title={title} description={description} canonical={canonical}>
      <Head>
        {posts.prev_page_url && <link rel="prev" href={posts.prev_page_url} />}
        {posts.next_page_url && <link rel="next" href={posts.next_page_url} />}
      </Head>

      <div ref={rootRef}>
        {/* ---------- HERO ---------- */}
        <section className="px-6 pt-16 pb-10 sm:px-10 sm:pt-24">
          <div className="mx-auto max-w-site">
            <p
              data-hero
              className="inline-flex items-center gap-2 text-xs font-bold tracking-[0.2em] text-site-secondary uppercase"
            >
              <span className="h-1.5 w-1.5 rounded-full bg-site-accent" />
              From the blog
            </p>
            <h1 data-hero className="site-h2 mt-6">
              {heading}
            </h1>
            <p data-hero className="site-lead mt-7 max-w-[52ch]">
              {lead}
            </p>
          </div>
        </section>

        {/* ---------- ACTIVE FILTER ---------- */}
        {(category || tag) && (
          <section className="px-6 pb-10 sm:px-10">
            <div className="mx-auto max-w-site">
              <SitePill>
                {category ? `Category: ${category.name}` : `Tag: ${tag!.name}`}
                <Link
                  href={route("blog.index")}
                  className="ml-2 text-site-ink-faint transition-colors hover:text-site-ink"
                  aria-label="Clear filter"
                >
                  &times;
                </Link>
              </SitePill>
            </div>
          </section>
        )}

        {/* ---------- POSTS ---------- */}
        <section className="px-6 pb-24 sm:px-10">
          <div className="mx-auto max-w-site">
            {posts.data.length === 0 ? (
              <div
                data-reveal
                className="rounded-site-card border border-site-border bg-site-surface py-20 text-center"
              >
                <p className="text-lg text-site-ink-soft">No posts published yet.</p>
              </div>
            ) : (
              <>
                <div className="grid gap-px overflow-hidden rounded-site-card border border-site-border bg-site-border sm:grid-cols-2 lg:grid-cols-3">
                  {posts.data.map((post) => (
                    <Link
                      key={post.id}
                      href={route("blog.show", post.slug)}
                      data-reveal
                      className="group flex flex-col bg-site-surface transition-colors hover:bg-site-alt-1/40"
                    >
                      {post.featured_image && (
                        <div className="aspect-video overflow-hidden">
                          <img
                            src={post.featured_image}
                            srcSet={post.featured_image_srcset || undefined}
                            sizes="(min-width: 1024px) 33vw, (min-width: 640px) 50vw, 100vw"
                            alt={post.title}
                            loading="lazy"
                            decoding="async"
                            className="h-full w-full object-cover transition-transform duration-500 group-hover:scale-[1.03]"
                          />
                        </div>
                      )}
                      <div className="flex flex-1 flex-col p-6 sm:p-8">
                        {post.category && (
                          <span className="mb-3 inline-flex items-center gap-2 text-xs font-bold tracking-[0.15em] text-site-secondary uppercase">
                            <span className="h-1 w-1 rounded-full bg-site-accent" />
                            {post.category.name}
                          </span>
                        )}
                        <h2 className="site-h5 transition-colors group-hover:text-site-secondary">
                          {post.title}
                        </h2>
                        {post.excerpt && (
                          <p className="mt-3 flex-1 text-sm leading-6 text-site-ink-soft line-clamp-3">
                            {post.excerpt}
                          </p>
                        )}
                        <div className="mt-5 flex items-center gap-2 text-xs text-site-ink-faint">
                          <span>{post.user.name}</span>
                          {post.published_at && (
                            <>
                              <span>&middot;</span>
                              <time>{new Date(post.published_at).toLocaleDateString()}</time>
                            </>
                          )}
                        </div>
                      </div>
                    </Link>
                  ))}
                </div>

                {posts.links.length > 3 && (
                  <div className="mt-14">
                    <SitePagination links={posts.links} />
                  </div>
                )}
              </>
            )}
          </div>
        </section>
      </div>
    </PublicLayout>
  );
}
