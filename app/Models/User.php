<?php

namespace App\Models;

use App\Ai\Models\AiProvider;
use App\Ai\Models\AiUsageLog;
use App\Services\PermissionCacheService;
use App\Traits\UserDataTrait;
use Database\Factories\UserFactory;
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

class User extends Authenticatable implements MustVerifyEmailContract
{
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

    public function notifications(): MorphMany
    {
        return $this->morphMany(Notification::class, 'notifiable')->latest();
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }

    public function aiProviders(): HasMany
    {
        return $this->hasMany(AiProvider::class);
    }

    public function aiUsageLogs(): HasMany
    {
        return $this->hasMany(AiUsageLog::class);
    }

    public function hasRole(string|array ...$roles): bool
    {
        $roles = array_merge(...array_map(fn ($r) => is_array($r) ? $r : [$r], $roles));

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

    public function hasVerifiedEmail(): bool
    {
        if (Setting::get('require_email_verification', '0') === '1') {
            return ! is_null($this->email_verified_at);
        }

        return true;
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
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

        return $this;
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (User $user) {
            if (empty($user->username) && ! empty($user->name)) {
                $user->username = $user->name;
            }
        });
    }

    public function casts(): array
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
