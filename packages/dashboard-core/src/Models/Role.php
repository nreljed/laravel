<?php

namespace Nreljed\DashboardCore\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Nreljed\DashboardCore\Database\Factories\RoleFactory;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string $guard_name
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Permission> $permissions
 * @property-read Collection<int, User> $users
 * @property-read int|null $permissions_count
 * @property-read int|null $users_count
 */
class Role extends Model
{
    /** @use HasFactory<RoleFactory> */
    use HasFactory;

    protected $fillable = ['name', 'slug', 'description', 'guard_name', 'sort_order'];

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function hasPermission(string $slug): bool
    {
        return $this->permissions()->where('slug', $slug)->exists();
    }

    public function hasPermissionTo(string $slug): bool
    {
        return $this->hasPermission($slug);
    }

    /**
     * Whether the role is managed by the config catalog (system roles are
     * locked against edits/deletion in the UI and enforced server-side).
     */
    public function isSystem(): bool
    {
        return array_key_exists($this->slug, (array) config('roles.roles', []));
    }

    protected static function newFactory(): RoleFactory
    {
        return RoleFactory::new();
    }
}
