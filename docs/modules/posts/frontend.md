# Posts Module — Frontend

## Pages

All pages in `Resources/js/Pages/`:

| Page | Route | Purpose |
|------|-------|---------|
| `Index.tsx` | `/dashboard/posts` | Post list (DataTable) |
| `Form.tsx` | `/dashboard/posts/create`, `/dashboard/posts/{post}/edit` | Create/edit post form |

## Post List (Index.tsx)

- DataTable with search and pagination
- Columns: Title, Status, Author, Created Date
- Row actions: Edit, Delete

## Post Form (Form.tsx)

- Title (text input)
- Content (rich text editor)
- Status (select: draft, published)
- Featured image upload
- Publish date scheduling
