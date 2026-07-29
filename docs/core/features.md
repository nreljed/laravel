# Feature Architecture

Each domain is self-contained with its own routes, controllers, models, migrations, and frontend pages.

## How It Works

1. **`FeatureRegistry`** (`app/Services/FeatureRegistry.php`) — singleton, delegates to feature service providers
2. **Feature Service Providers** (e.g., `BlogServiceProvider`) — register routes, permissions, nav items
3. **`HandleInertiaRequests`** calls `FeatureRegistry::getAllNavItems()` and `getAllSharedData()` to share props

## Current Features

| Feature | Slug    | Permissions                                                | Frontend Pages                           |
| ------- | ------- | ---------------------------------------------------------- | ---------------------------------------- |
| Blog    | `posts` | `posts.view`, `posts.create`, `posts.edit`, `posts.delete` | Index, Form, Dashboard, Categories, Tags |

## Adding a New Feature

1. Create `app/{Name}/` with Controllers/, Models/, Routes/, Resources/js/Pages/
2. Create `{Name}ServiceProvider.php` — register routes, permissions, nav items in `boot()`
3. Register in `bootstrap/providers.php`
4. Add permission slugs to `database/seeders/RolesAndPermissionsSeeder.php`
5. Register the service provider in `FeatureRegistry`

## Registering Notification Types

Features can register notification types via `FeatureRegistry::getAllNotificationTypes()`. Each type defines a label, group, default channels, and optional email template:

```php
// In your feature's ServiceProvider or FeatureRegistry
public function getNotificationTypes(): array
{
    return [
        'order.placed' => [
            'label' => 'Order Placed',
            'group' => 'Orders',
            'default_channels' => ['in_app', 'email'],
            'default_enabled' => true,
            'email_template' => 'emails.order-placed',
        ],
    ];
}
```

Then dispatch with:

```php
use App\Notifications\AppNotification;

$user->notify(new AppNotification('order.placed', [
    'title' => 'New order received',
    'message' => "Order #{$order->id}",
    'link' => route('dashboard.orders.show', $order),
    'severity' => 'info',
    'module' => 'orders',
]));
```

The `AppServiceProvider` creating hook auto-populates structured columns (`title`, `message`, `link`, `severity`, `module`) from the data array.

See [Notifications](notifications.md) for the full notification architecture.

## Feature File Structure

```
app/Blog/
  BlogServiceProvider.php     # Registers routes, permissions, nav items
  Controllers/                # PostController, PostCategoryController, etc.
  Models/                     # Post, PostCategory, PostTag, ContentBlock
  Routes/
    web.php                   # Admin routes (dashboard/posts/*)
    public.php                # Public routes (blog/*)
  Resources/js/Pages/
    Index.tsx                 # Posts list
    Form.tsx                  # Create/edit
    Dashboard.tsx             # Blog-specific dashboard
    Categories/Index.tsx
    Tags/Index.tsx
    Public/Index.tsx          # Public blog listing
    Public/Show.tsx           # Public post view
  database/migrations/
```
