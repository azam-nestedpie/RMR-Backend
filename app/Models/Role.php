<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    protected $fillable = [
        'name',
        'description',
        'created_by',
        'updated_by',
    ];

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions', 'role_id', 'permission_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_roles', 'role_id', 'user_firebase_uid')
            ->withPivot(['created_by', 'updated_by'])
            ->withTimestamps();
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by', 'firebase_uid');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by', 'firebase_uid');
    }

    public static function idByName(string $name): ?int
    {
        return static::query()->where('name', $name)->value('id');
    }

    public function scopeNamed(Builder $query, string $name): Builder
    {
        return $query->where('name', $name);
    }
}
