<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Traits\UserDataTrait;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'username', 'display_name', 'first_name', 'last_name', 'nickname', 'bio', 'description', 'avatar', 'social_links', 'phone', 'url'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, UserDataTrait;

    protected $appends = ['avatar_url'];

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
        foreach ($this->roles as $role) {
            if ($role->hasPermission($slug)) {
                return true;
            }
        }

        return $this->permissions()->where('slug', $slug)->exists();
    }

    public function hasPermissionTo(string $slug): bool
    {
        return $this->hasPermission($slug);
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    public function assignRole(string $slug): self
    {
        $role = Role::where('slug', $slug)->first();

        if ($role) {
            $this->roles()->syncWithoutDetaching([$role->id]);
        }

        return $this;
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
