# User Model

`app/Models/User.php`

Extends `Illuminate\Foundation\Auth\User` (Authenticatable). Implements `MustVerifyEmailContract`.

## Fillable

`name`, `email`, `password`, `username`, `display_name`, `first_name`, `last_name`, `nickname`, `bio`, `description`, `avatar`, `social_links`, `phone`, `url`

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

Returns all display name formatting options:

```php
[
    'username'  => $user->name,
    'firstname' => $user->first_name,
    'lastname'  => $user->last_name,
    'firstlast' => 'First Last',
    'lastfirst' => 'Last First',
    'nickname'  => $user->nickname,
    'display'   => $user->display_name,
]
```

Used by both `ProfileController` and `HandleInertiaRequests::buildAuthUser()` to avoid duplicating the logic.

### `assignRole(string $roleSlug)`

Syncs a single role without detaching other roles. Used by `scripts/create-admin.php`.

## Casts

- `email_verified_at`: datetime
- `password`: hashed
- `social_links`: array
