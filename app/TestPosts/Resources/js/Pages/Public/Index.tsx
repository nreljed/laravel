import { Link } from "@inertiajs/react";
import PublicLayout from "@/Layouts/PublicLayout";
import PaginationLinks from "@/Components/shared/pagination-links";
import { MapPin } from "lucide-react";

interface Author {
  id: number;
  name: string;
}

interface TestPost {
  id: number;
  title: string;
  slug: string;
  excerpt: string | null;
  featured_image: string | null;
  published_at: string | null;
  latitude: number | null;
  longitude: number | null;
  user: Author;
}

interface PaginatedTestPosts {
  data: TestPost[];
  links: { url: string | null; label: string; active: boolean }[];
}

interface TestPostsIndexProps {
  testPosts: PaginatedTestPosts;
}

export default function TestPostsPublicIndex({ testPosts }: TestPostsIndexProps) {
  return (
    <PublicLayout
      title="Test Posts"
      description="Explore our test posts with interactive maps and galleries."
    >
      <div className="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
        <div className="mb-12 text-center">
          <h1 className="text-4xl font-bold tracking-tight text-foreground">Test Posts</h1>
          <p className="mt-4 text-lg text-muted-foreground">
            Explore our test posts with interactive maps and galleries.
          </p>
        </div>

        {testPosts.data.length === 0 ? (
          <div className="py-16 text-center">
            <p className="text-lg text-muted-foreground">No test posts published yet.</p>
          </div>
        ) : (
          <>
            <div className="grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
              {testPosts.data.map((testPost) => (
                <Link
                  key={testPost.id}
                  href={route("test-posts.show", testPost.slug)}
                  className="group flex flex-col overflow-hidden rounded-lg border border-border bg-card transition-colors hover:bg-accent/50"
                >
                  {testPost.featured_image && (
                    <div className="aspect-video overflow-hidden">
                      <img
                        src={testPost.featured_image}
                        alt={testPost.title}
                        className="h-full w-full object-cover transition-transform group-hover:scale-105"
                      />
                    </div>
                  )}
                  <div className="flex flex-1 flex-col p-6">
                    <h2 className="mb-2 text-xl font-semibold text-foreground group-hover:text-primary">
                      {testPost.title}
                    </h2>
                    {testPost.excerpt && (
                      <p className="mb-4 flex-1 text-sm text-muted-foreground line-clamp-3">
                        {testPost.excerpt}
                      </p>
                    )}
                    <div className="flex items-center gap-3 text-xs text-muted-foreground">
                      <span>{testPost.user.name}</span>
                      {testPost.published_at && (
                        <>
                          <span>&middot;</span>
                          <time>{new Date(testPost.published_at).toLocaleDateString()}</time>
                        </>
                      )}
                      {testPost.latitude != null && testPost.longitude != null && (
                        <>
                          <span>&middot;</span>
                          <span className="flex items-center gap-1">
                            <MapPin className="h-3 w-3" />
                            Location
                          </span>
                        </>
                      )}
                    </div>
                  </div>
                </Link>
              ))}
            </div>

            {testPosts.links.length > 3 && (
              <div className="mt-12">
                <PaginationLinks links={testPosts.links} />
              </div>
            )}
          </>
        )}
      </div>
    </PublicLayout>
  );
}
