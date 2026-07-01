# Posts Module

Create and manage blog posts and articles.

- **Slug**: `posts`
- **Version**: `1.0.0`
- **Dependencies**: None
- **Has Settings**: No
- **Nav Label**: Content

## Permissions

| Slug | Name |
|------|------|
| `posts.view` | View Posts |
| `posts.create` | Create Posts |
| `posts.edit` | Edit Posts |
| `posts.delete` | Delete Posts |

## Routes

| File | Prefix | Auth | Middleware |
|------|--------|------|------------|
| `Routes/web.php` | `/dashboard/posts` | Required | `posts.view` |

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

## Gallery Config

- `gallery` — max 20 images, upload path `posts`, hidden from media library

## Policy

| Policy | Model | Scope |
|--------|-------|-------|
| `PostPolicy` | `Post` | Owner or admin |

## Migration Paths

Returns `[]` — no module-specific migrations. The `posts` table is created via application-level migrations.

## Notification Types

| Type | Channels |
|------|----------|
| `post.published` | in_app only |
| `post.scheduled` | in_app only |

## User Cleanup

On user deletion, all posts by that user are deleted.
