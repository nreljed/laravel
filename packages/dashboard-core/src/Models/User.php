<?php

namespace Nreljed\DashboardCore\Models;

use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Ai\Concerns\HasConversations;
use Laravel\Sanctum\HasApiTokens;
use Nreljed\DashboardCore\Ai\Models\AiProvider;
use Nreljed\DashboardCore\Ai\Models\AiUsageLog;
use Nreljed\DashboardCore\Database\Factories\UserFactory;
use Nreljed\DashboardCore\Notifications\ResetPassword;
use Nreljed\DashboardCore\Services\PermissionCacheService;
use Nreljed\DashboardCore\Traits\UserDataTrait;

class User extends Authenticatable implements MustVerifyEmailContract
{
    #[\Override]
    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new ResetPassword($token));
    }

    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasConversations, HasFactory, MustVerifyEmail, Notifiable, UserDataTrait;

    protected $fillable = [
        'username',
        'name',
        'display_name',
        'email',
        'password',
        'first_name',
        'last_name',
        'url',
        'bio',
        'avatar',
        'phone',
        'social_links',
        'billing_address',
        'shipping_address',
        'contact_email',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $appends = ['avatar_url'];

    /** Per-request memoization of the admin bypass check. */
    protected ?bool $isAdminMemo = null;

    /** @return MorphMany<Notification, $this> */
    public function notifications(): MorphMany
    {
        return $this->morphMany(Notification::class, 'notifiable')->latest();
    }

    /** @return BelongsToMany<Role, $this> */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    /** @return BelongsToMany<Permission, $this> */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }

    /** @return HasMany<AiProvider, $this> */
    public function aiProviders(): HasMany
    {
        return $this->hasMany(AiProvider::class);
    }

    /** @return HasMany<AiUsageLog, $this> */
    public function aiUsageLogs(): HasMany
    {
        return $this->hasMany(AiUsageLog::class);
    }

    public function hasRole(string|array ...$roles): bool
    {
        $roles = array_merge(...array_map(fn ($r) => is_array($r) ? $r : [$r], $roles));

        if ($this->relationLoaded('roles')) {
            return $this->roles->contains(fn (Role $role) => in_array($role->slug, $roles, true));
        }

        return $this->roles()->whereIn('slug', $roles)->exists();
    }

    public function hasPermission(string $slug): bool
    {
        $cache = app(PermissionCacheService::class);

        return in_array($slug, $cache->getUserPermissionSlugs($this->id), true);
    }

    public function hasPermissionTo(string $slug): bool
    {
        return $this->hasPermission($slug);
    }

    #[\Override]
    public function canAny($abilities, $arguments = []): bool
    {
        if (is_iterable($abilities)) {
            $cache = app(PermissionCacheService::class);
            $userSlugs = $cache->getUserPermissionSlugs($this->id);

            return array_any(
                iterator_to_array($abilities),
                fn (string $slug) => in_array($slug, $userSlugs, true),
            );
        }

        return parent::canAny($abilities, $arguments);
    }

    public function hasAllPermissions(iterable $permissions): bool
    {
        $cache = app(PermissionCacheService::class);
        $userSlugs = $cache->getUserPermissionSlugs($this->id);

        return array_all(
            iterator_to_array($permissions),
            fn (string $slug) => in_array($slug, $userSlugs, true),
        );
    }

    #[\Override]
    public function hasVerifiedEmail(): bool
    {
        if (Setting::get('require_email_verification', '0') === '1') {
            return ! is_null($this->email_verified_at);
        }

        return true;
    }

    public function isAdmin(): bool
    {
        return $this->isAdminMemo ??= $this->hasRole('admin');
    }

    public function invalidatePermissionCache(): void
    {
        $this->isAdminMemo = null;
        $this->unsetRelation('roles');
        $this->unsetRelation('permissions');
        app(PermissionCacheService::class)->forgetUser($this->id);
    }

    /** Reset the per-request admin memo after a database reload. */
    #[\Override]
    public function refresh(): static
    {
        $this->isAdminMemo = null;

        return parent::refresh();
    }

    public function getDisplayNameOptions(): array
    {
        $options = [];

        $options['username'] = $this->username;

        if (! empty($this->first_name)) {
            $options['first'] = $this->first_name;
        }
        if (! empty($this->last_name)) {
            $options['last'] = $this->last_name;
        }
        if (! empty($this->first_name) && ! empty($this->last_name)) {
            $options['first_last'] = $this->first_name.' '.$this->last_name;
            $options['last_first'] = $this->last_name.' '.$this->first_name;
        }

        $displayName = $this->display_name ?: $this->name;
        if (! in_array($displayName, $options)) {
            $options['display'] = $displayName;
        }

        return $options;
    }

    public function assignRole(string $slug): self
    {
        $role = Role::where('slug', $slug)->first();

        if ($role) {
            $this->roles()->syncWithoutDetaching([$role->id]);
        }

        $this->invalidatePermissionCache();

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getReferencedPaths(): array
    {
        return $this->avatar ? ['avatars/'.$this->avatar] : [];
    }

    #[\Override]
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (User $user) {
            if (empty($user->username) && ! empty($user->name)) {
                $user->username = $user->name;
            }
        });
    }

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    #[\Override]
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'phone' => 'string',
            'social_links' => 'array',
            'billing_address' => 'array',
            'shipping_address' => 'array',
        ];
    }
}
