<?php

namespace App\Models;

use App\Services\PermissionCacheService;
use App\Traits\UserDataTrait;
use Database\Factories\UserFactory;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmailContract
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, MustVerifyEmail, Notifiable, UserDataTrait;

    protected $fillable = [
        'username',
        'name',
        'display_name',
        'email',
        'password',
        'first_name',
        'last_name',
        'nickname',
        'url',
        'bio',
        'description',
        'avatar',
        'phone',
        'social_links',
        'billing_address',
        'shipping_address',
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

    public function roles()
    {
        return $this->belongsToMany(Role::class);
    }

    public function permissions()
    {
        return $this->belongsToMany(Permission::class);
    }

    public function hasRole(string|array $roles): bool
    {
        $roles = is_array($roles) ? $roles : func_get_args();

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

            foreach ($abilities as $slug) {
                if (in_array($slug, $userSlugs, true)) {
                    return true;
                }
            }

            return false;
        }

        return parent::canAny($abilities, $arguments);
    }

    public function hasAllPermissions(iterable $permissions): bool
    {
        $cache = app(PermissionCacheService::class);
        $userSlugs = $cache->getUserPermissionSlugs($this->id);

        foreach ($permissions as $slug) {
            if (! in_array($slug, $userSlugs, true)) {
                return false;
            }
        }

        return true;
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

        $options['username'] = $this->name;

        if (! empty($this->first_name)) {
            $options['firstname'] = $this->first_name;
        }
        if (! empty($this->last_name)) {
            $options['lastname'] = $this->last_name;
        }
        if (! empty($this->first_name) && ! empty($this->last_name)) {
            $options['firstlast'] = $this->first_name.' '.$this->last_name;
            $options['lastfirst'] = $this->last_name.' '.$this->first_name;
        }
        if (! empty($this->nickname)) {
            $options['nickname'] = $this->nickname;
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
                $user->username = str($user->name)->slug('_')->toString();
            }
        });
    }

    public function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'social_links' => 'array',
            'billing_address' => 'array',
            'shipping_address' => 'array',
        ];
    }
}
