import { SiteAvatar } from "./SiteAvatar";
import { SiteCard, SiteCardTitle } from "./SiteCard";
import { formatSiteDate } from "./format-date";

interface BlogCardPost {
  id: number;
  title: string;
  slug: string;
  excerpt: string | null;
  featured_image: string | null;
  featured_image_srcset?: string | null;
  published_at: string | null;
  user: {
    name: string;
    avatar?: string | null;
    avatar_url?: string | null;
  };
  category: { name: string } | null;
}

interface BlogCardProps {
  post: BlogCardPost;
  reveal?: boolean;
}

// Blog archive card: featured image, category eyebrow, title, excerpt, byline.
// A SiteCard cell, so it lines up in a SiteCardGrid. Static by design — no image
// zoom and no hover tint.
export function BlogCard({ post, reveal }: BlogCardProps) {
  const publishedAt = formatSiteDate(post.published_at, "short");

  return (
    <SiteCard href={route("blog.show", post.slug)} reveal={reveal}>
      {post.featured_image && (
        <div className="aspect-video">
          <img
            src={post.featured_image}
            srcSet={post.featured_image_srcset || undefined}
            sizes="(min-width: 1024px) 33vw, (min-width: 640px) 50vw, 100vw"
            alt={post.title}
            loading="lazy"
            decoding="async"
            className="h-full w-full object-cover"
          />
        </div>
      )}
      <div className="flex flex-1 flex-col p-site-lg sm:p-site-xl">
        {post.category && (
          <span className="mb-site-sm inline-flex items-center gap-site-xs text-xs font-bold tracking-[0.15em] text-site-secondary uppercase">
            <span className="h-1 w-1 rounded-full bg-site-accent" />
            {post.category.name}
          </span>
        )}
        <SiteCardTitle>{post.title}</SiteCardTitle>
        {post.excerpt && (
          <p className="mt-site-sm flex-1 text-sm leading-6 text-site-ink-soft line-clamp-3">
            {post.excerpt}
          </p>
        )}
        <div className="mt-site-lg flex items-center gap-site-sm text-xs text-site-ink-faint">
          <SiteAvatar name={post.user.name} src={post.user.avatar_url} />
          <span className="truncate font-medium text-site-ink">{post.user.name}</span>
          {publishedAt && (
            <>
              <span aria-hidden="true">&middot;</span>
              <time className="shrink-0" dateTime={post.published_at ?? undefined}>
                {publishedAt}
              </time>
            </>
          )}
        </div>
      </div>
    </SiteCard>
  );
}
