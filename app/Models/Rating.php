<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rating extends Model
{
    protected $fillable = [
        'firebase_uuid',
        'rater_firebase_uid',
        'external_user_id',
        'rep_firebase_uid',
        'rating_request_id',
        'from_external_link',
        'rated_at',
        'average_score',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'from_external_link' => 'boolean',
        'rated_at' => 'datetime',
        'average_score' => 'float',
    ];

    public function rater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rater_firebase_uid', 'firebase_uid');
    }

    public function externalUser(): BelongsTo
    {
        return $this->belongsTo(ExternalUser::class, 'external_user_id');
    }

    public function rep(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rep_firebase_uid', 'firebase_uid');
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(RatingRequest::class, 'rating_request_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(RatingItem::class, 'rating_id');
    }

    public function edits(): HasMany
    {
        return $this->hasMany(RatingEdit::class, 'rating_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by', 'firebase_uid');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by', 'firebase_uid');
    }

    public function scopeForRep(Builder $query, string $firebaseUid): Builder
    {
        return $query->where('rep_firebase_uid', $firebaseUid);
    }

    public function scopeGivenBy(Builder $query, string $firebaseUid): Builder
    {
        return $query->where('rater_firebase_uid', $firebaseUid);
    }
}
