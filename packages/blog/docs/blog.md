# Blog Feature

`Nreljed\Platform\Blog` is the in-core example of a self-contained feature provider —
registered in `config/features.php` `providers` by default. Use it as the reference for building a
new feature (see [Feature Provider](feature-provider.md)).

## Models

| Model          | Table             | Notes                                                                                                                                                                                                                                                                                                                                                                                                                 |
| -------------- | ----------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `Post`         | `posts`           | `user_id`, `title`, `slug`, `excerpt`, `content` (rich text), `status` (`draft`/`published`/`future`), `published_at`, `category_id`, `metadata` (JSON: `meta_title`, `meta_description`, `og_image`, `focus_keyword`), featured image + gallery (media system). Auto-slug, auto `published_at`, image cleanup on delete, `getReferencedPaths()` for orphan-media scans. Relations: `user()`, `category()`, `tags()`. |
| `PostCategory` | `post_categories` | `name`, `slug`, `description`, `parent_id`, `order`, `depth`. Self-referencing tree; auto-slug; SEO columns. Relations: `children()`, `parent()`, `posts()`.                                                                                                                                                                                                                                                          |
| `PostTag`      | `post_tags`       | `name`, `slug`, `description`, `order`. Relation: `posts()` via `post_tag_post` pivot.                                                                                                                                                                                                                                                                                                                                |

## Controllers & Routes

Admin routes (`Routes/web.php`) — group `web + auth + verified + throttle:60,1`, prefix
`dashboard/posts`, names `dashboard.posts.*`. Every route is individually gated with
`permission:posts.*`:

| Area       | Endpoints                                                                                                                                                  |
| ---------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Posts      | `GET /` (index), `GET /dashboard`, `GET /create`, `POST /`, `GET /{post}/edit`, `PUT /{post}`, `PUT /{post}/status`, `DELETE /{post}`, `POST /bulk/action` |
| Media      | `POST /upload-image`, `DELETE /delete-image`                                                                                                               |
| SEO        | `POST /seo/generate` (single, AI), `POST /seo/bulk`                                                                                                        |
| Categories | `GET/POST /categories`, `POST /categories/reorder`, `POST /categories/bulk`, `PUT/DELETE /categories/{postCategory}`                                       |
| Tags       | `GET/POST /tags`, `POST /tags/reorder`, `POST /tags/bulk`, `PUT/DELETE /tags/{postTag}`                                                                    |

Public routes (`Routes/public.php`) — `web`, prefix `blog`: `GET /` (index),
`GET /{slug}` (show).

## Permissions

`posts.view`, `posts.create`, `posts.edit`, `posts.delete`, `posts.manage` (all posts by any
author), `posts.categories.manage`, `posts.tags.manage`. Enforced by the `permission:posts.*`
middleware + `PostPolicy` (`viewAny`/`create`/`update`/`delete`, owner-or-admin with
`posts.manage`).

## UI

- **Admin pages** (`Resources/js/Pages/`): `Dashboard.tsx` (stats), `Index.tsx` (search,
  status/category filters, bulk actions, pagination), `Form.tsx` (tabbed Content / Blocks /
  SEO with rich text editor, featured image, category/tag selectors), `Categories/Index.tsx`,
  `Tags/Index.tsx`.
- **Public pages**: `Blog/Public/Index.tsx`, `Blog/Public/Show.tsx` — rendered under the
  host's `PublicLayout`.
- **Editor**: Lexical-based rich text editor (`resources/js/Components/editor/`) with image,
  layout columns, tables, YouTube embeds, slash commands, and TOC.
- **Notification types**: `post.published`, `post.scheduled` (in_app, default on).
- **Dashboard**: `getDashboardData()` (total/published/draft) + `getAnalyticsSeries()` (29-day
  new-post series).
- **Gallery config**: `getGalleryConfig()` wires the media library (upload/delete actions,
  `posts` folder, thumbnail key).

## Related

- [Feature Provider](feature-provider.md) — the contract this provider implements.
- [Notifications](notifications.md) — `post.published`/`post.scheduled` dispatch.
- [Settings](settings.md) — formatting/meta config used by the SEO panel.
