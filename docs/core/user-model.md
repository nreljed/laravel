# User Model

`app/Models/User.php`

Extends `Illuminate\Foundation\Auth\User` (Authenticatable). Implements `MustVerifyEmailContract`.

## Fillable

`username`, `name`, `display_name`, `email`, `password`, `first_name`, `last_name`, `bio`, `avatar`, `social_links`, `phone`, `url`, `contact_email`, `billing_address`, `shipping_address`

## Field Roles

| Field           | Purpose                                    | Editable by User        |
| --------------- | ------------------------------------------ | ----------------------- |
| `username`      | Login ID, unique, never changed            | Never                   |
| `name`          | Display name (what others see)             | Yes                     |
| `first_name`    | Legal/real first name                      | Yes                     |
| `last_name`     | Legal/real last name                       | Yes                     |
| `display_name`  | Derived from user-selected option          | User picks from options |
| `bio`           | Biographical info                          | Yes                     |
| `contact_email` | Public contact email (separate from login) | Yes                     |

## Hidden

`password`, `remember_token`

## Appends

`avatar_url` (accessor on the UserData trait)

## Traits

`HasFactory`, `MustVerifyEmail`, `Notifiable`, `UserDataTrait`

## Relations

### `roles()` — BelongsToMany(Role)

Many-to-many via `role_user` pivot table.

### `permissions()` — BelongsToMany(Permission)

Direct permissions (not through roles). Many-to-many via `permission_user` pivot table.

## Methods

### `hasRole(string|array $roleSlug): bool`

Checks if user has any of the given role slugs.

### `hasPermission(string $slug): bool`

First checks roles' permissions, then direct permissions. Returns true if found in either.

### `hasPermissionTo(string $slug): bool`

Alias for `hasPermission()`.

### `hasVerifiedEmail(): bool`

Respects the `require_email_verification` setting. If disabled, returns true regardless of `email_verified_at`.

### `isAdmin(): bool`

Checks `hasRole('admin')`.

### `getDisplayNameOptions(): array`

Returns all display name formatting options based on filled profile fields:

```php
[
    'username'   => $user->username,
    'first'      => $user->first_name,
    'last'       => $user->last_name,
    'first_last' => 'First Last',
    'last_first' => 'Last First',
    'display'    => $user->display_name,  // if differs from above
]
```

Options with empty source values are excluded. Used by `UserAccountController` and `HandleInertiaRequests::buildAuthUser()`.

### `assignRole(string $roleSlug)`

Syncs a single role without detaching other roles.

## Casts

- `email_verified_at`: datetime
- `password`: hashed
- `social_links`: array
- `billing_address`: array
- `shipping_address`: array
