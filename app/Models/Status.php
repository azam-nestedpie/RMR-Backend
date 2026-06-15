<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Status extends Model
{
    protected $fillable = [
        'name',
        'created_by',
        'updated_by',
    ];

    private static array $idCache = [];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'firebase_uid');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by', 'firebase_uid');
    }

    public static function idByName(string $name): ?int
    {
        if (! array_key_exists($name, self::$idCache)) {
            self::$idCache[$name] = static::query()->where('name', $name)->value('id');
        }

        return self::$idCache[$name];
    }

    public function scopeNamed(Builder $query, string $name): Builder
    {
        return $query->where('name', $name);
    }
}
