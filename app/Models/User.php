<?php

namespace App\Models;

use App\Services\PermissionCacheService;
use App\Traits\UserDataTrait;
use Database\Factories\UserFactory;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\App;

#[Fillable(['name', 'email', 'contact_email', 'password', 'username', 'display_name', 'first_name', 'last_name', 'nickname', 'bio', 'description', 'avatar', 'social_links', 'phone', 'url'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmailContract
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, MustVerifyEmail, Notifiable, UserDataTrait;

    protected $appends = ['avatar_url'];

    /**
     * Override the default notifications() to use our custom Notification model.
     */
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
        $cache = $this->getPermissionCache();

        return in_array($slug, $cache->getUserPermissionSlugs($this->id), true);
    }

    public function hasPermissionTo(string $slug): bool
    {
        return $this->hasPermission($slug);
    }

    public function canAny($abilities, $arguments = []): bool
    {
        if (is_iterable($abilities)) {
            $cache = $this->getPermissionCache();
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
        $cache = $this->getPermissionCache();
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

    protected function getPermissionCache(): PermissionCacheService
    {
        return App::make(PermissionCacheService::class);
    }

    public function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'social_links' => 'array',
        ];
    }
}
