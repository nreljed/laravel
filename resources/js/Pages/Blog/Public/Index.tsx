import { Head, Link, usePage } from "@inertiajs/react";
import PublicLayout from "../../../Layouts/PublicLayout";
import { SitePill } from "../../../Components/site/SitePill";
import { SitePagination } from "../../../Components/site/SitePagination";

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

      <div className="mx-auto w-full max-w-site px-4 py-16 sm:px-6 lg:px-8">
        <div className="mb-12 text-center">
          <p className="text-sm font-medium tracking-[0.2em] text-site-secondary uppercase">
            From the blog
          </p>
          <h1 className="mt-4 font-site-display text-4xl font-bold tracking-tight text-site-ink sm:text-5xl">
            {category ? category.name : tag ? tag.name : "Blog"}
          </h1>
          <p className="mx-auto mt-4 max-w-2xl text-lg text-site-ink-soft">
            {category
              ? category.meta_description || "Articles in this category."
              : tag
                ? `Posts tagged with ${tag.name}.`
                : "Read our latest articles and insights."}
          </p>
        </div>

        {(category || tag) && (
          <div className="mb-8 text-center">
            <span className="inline-flex items-center gap-2">
              <SitePill>
                {category ? `Category: ${category.name}` : `Tag: ${tag!.name}`}
                <Link
                  href={route("blog.index")}
                  className="ml-1 text-site-ink-faint hover:text-site-ink"
                  aria-label="Clear filter"
                >
                  &times;
                </Link>
              </SitePill>
            </span>
          </div>
        )}

        {posts.data.length === 0 ? (
          <div className="py-16 text-center">
            <p className="text-lg text-site-ink-soft">No posts published yet.</p>
          </div>
        ) : (
          <>
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
              {posts.data.map((post) => (
                <Link
                  key={post.id}
                  href={route("blog.show", post.slug)}
                  className="group flex flex-col overflow-hidden rounded-site-card bg-site-surface transition-colors hover:bg-site-alt-1/40"
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
                        className="h-full w-full object-cover transition-transform group-hover:scale-105"
                      />
                    </div>
                  )}
                  <div className="flex flex-1 flex-col p-6">
                    {post.category && (
                      <span className="mb-2 text-xs font-medium uppercase tracking-wider text-site-secondary">
                        {post.category.name}
                      </span>
                    )}
                    <h2 className="mb-2 font-site-display text-xl font-semibold text-site-ink transition-colors group-hover:text-site-secondary">
                      {post.title}
                    </h2>
                    {post.excerpt && (
                      <p className="mb-4 flex-1 text-sm text-site-ink-soft line-clamp-3">
                        {post.excerpt}
                      </p>
                    )}
                    <div className="flex items-center gap-2 text-xs text-site-ink-faint">
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
    </PublicLayout>
  );
}
