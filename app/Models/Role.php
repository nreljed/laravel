<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'guard_name'];

    public function permissions()
    {
        return $this->belongsToMany(Permission::class);
    }

    public function users()
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
}
