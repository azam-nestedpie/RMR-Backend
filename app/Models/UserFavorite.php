<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserFavorite extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'user_firebase_uid',
        'favorite_user_firebase_uid',
        'created_by',
        'updated_by',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_firebase_uid', 'firebase_uid');
    }

    public function favoriteUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'favorite_user_firebase_uid', 'firebase_uid');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'firebase_uid');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by', 'firebase_uid');
    }
}
