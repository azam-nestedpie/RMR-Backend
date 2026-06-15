<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    protected $fillable = [
        'firebase_uuid',
        'to_user_firebase_uid',
        'from_user_firebase_uid',
        'message',
        'screen',
        'tab_index',
        'is_for_external_rating',
        'is_read',
        'read_at',
        'sent_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_for_external_rating' => 'boolean',
        'is_read' => 'boolean',
        'read_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_firebase_uid', 'firebase_uid');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_firebase_uid', 'firebase_uid');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by', 'firebase_uid');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by', 'firebase_uid');
    }

    public function scopeForRecipient(Builder $query, string $firebaseUid): Builder
    {
        return $query->where('to_user_firebase_uid', $firebaseUid);
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->where('is_read', false);
    }
}
