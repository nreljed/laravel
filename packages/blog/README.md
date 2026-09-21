# nreljed/blog

Blog feature for the Nreljed dashboard platform — self-contained
`FeatureProvider` (slug `posts`) shipping the blog domain:

- **Posts** CRUD, dashboard analytics, future/scheduled publishing, SEO
  metadata generation (AI), galleries and media library scoping.
- **Categories** (hierarchical) and **Tags** management.
- **Public blog** routes under `/blog` (index, show, signed preview).
- **Notifications** (`post.published`, `post.scheduled`) with shipped Blade
  email templates under the `posts::` view namespace.
- **Settings** section on the unified admin settings page.

The package is a Composer vcs install: `composer require nreljed/blog`. It
depends on the platform kernel (`nreljed/platform`) for the
`FeatureProvider` contract, shared services and the frontend component kit.

## Layout

```
src/
  BlogServiceProvider.php    # FeatureProvider (slug: posts, views: posts::)
  Contracts/                 # Frozen copy of the platform FeatureProvider contract
  Controllers/ Models/ Requests/ Routes/ Services/
  Observers/ Policies/ Jobs/ Console/Commands/ Database/Factories/
  Resources/js               # Frontend kit — exports registerBlogPages(); apps alias '@nreljed/blog' here
  Resources/views/email      # posts:: email templates
database/migrations/         # Posts tables
tests/                       # Structural + contract tests (no framework boot)
```

## Routes (stable, unchanged from the kernel-bundled era)

| Prefix                 | Namespace          | Purpose                    |
| ---------------------- | ------------------ | -------------------------- |
| `dashboard/posts`      | `dashboard.posts.` | Admin CRUD + categories/tags + SEO + upload |
| `dashboard/admin/settings/posts` | `dashboard.posts.settings.` | Feature settings |
| `blog`                 | `blog.`            | Public blog (index/show/preview) |

## Frontend aliases

Apps wire `@nreljed/blog` → `vendor/nreljed/blog/src/Resources/js` in their
`vite.config.js` + `tsconfig.json` (`@nreljed/blog` and `@nreljed/blog/*`), then
spread `registerBlogPages()` from `@nreljed/blog/pages` into their page map
under the `posts/` prefix. The legacy `@blog` / `blog.aliases.js` scheme is
superseded — consumers use the `@nreljed/*` namespace with platform/blog aliases.