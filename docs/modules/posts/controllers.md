# Posts Module — Controllers

| Controller | File | Purpose |
|------------|------|---------|
| `PostController` | `Controllers/PostController.php` | Full CRUD |

## PostController

Routes under `/dashboard/posts`:

| Method | Route | Purpose | Access |
|--------|-------|---------|--------|
| `index()` | `GET /` | List posts | `posts.view` |
| `create()` | `GET /create` | Show create form | `posts.create` |
| `store()` | `POST /` | Create post | `posts.create` |
| `edit()` | `GET /{post}/edit` | Show edit form | `posts.edit` |
| `update()` | `PUT /{post}` | Update post | `posts.edit` |
| `destroy()` | `DELETE /{post}` | Delete post | `posts.delete` |

Authorization via `PostPolicy` — all actions check ownership or admin role.
