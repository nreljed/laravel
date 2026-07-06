# Posts Module

Create and manage blog posts and articles.

- **Slug**: `posts`
- **Version**: `1.0.0`
- **Dependencies**: None
- **Has Settings**: No
- **Nav Group Label**: Content

## Sub-Documents

| Document | Description |
|----------|-------------|
| [Controllers](controllers.md) | Controller methods reference |
| [Frontend](frontend.md) | Frontend pages and components |

## Quick Reference

| Attribute | Value |
|-----------|-------|
| **Permissions** | `posts.view`, `create`, `edit`, `delete` |
| **Route Files** | `web.php` (auth) |
| **Dashboard Stats** | `total`, `published`, `draft` |
| **Gallery Config** | `gallery` (max 20, hidden from library) |
| **Notification Types** | `post.published`, `post.scheduled` |
| **Migration Paths** | `[]` — no module-specific migrations |

## Routes

| File | Prefix | Middleware | Purpose |
|------|--------|------------|---------|
| `Routes/web.php` | `/dashboard/posts` | `auth`, `verified`, `posts.view` | Full CRUD |

### Web Routes

Full CRUD: index, create, store, edit, update, destroy.

## Nav Items

```
Content                (SidebarGroupLabel)
  └─ Posts             (collapsible parent)
      ├─ All Posts
      └─ Add Post
```

## Dashboard Stats

- `total` — All posts count
- `published` — Published status count
- `draft` — Draft status count

## Policy

| Policy | Model | Scope |
|--------|-------|-------|
| `PostPolicy` | `Post` | Owner or admin |

Policy methods: `viewAny`, `view`, `create`, `update`, `delete` — all check ownership or admin role.

## User Cleanup

On user deletion, all posts by that user are deleted.
