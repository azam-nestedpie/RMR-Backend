<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Connection extends Model
{
    protected $fillable = [
        'firebase_uuid',
        'user_a_firebase_uid',
        'user_b_firebase_uid',
        'connected_by_uid',
        'source_request_id',
        'connected_at',
        'disconnected_at',
        'disconnect_reason',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'connected_at' => 'datetime',
        'disconnected_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Connection $connection): void {
            $connection->firebase_uuid ??= (string) Str::uuid();
        });
    }

    public function userA(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_a_firebase_uid', 'firebase_uid');
    }

    public function userB(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_b_firebase_uid', 'firebase_uid');
    }

    public function sourceRequest(): BelongsTo
    {
        return $this->belongsTo(ConnectionRequest::class, 'source_request_id');
    }

    public function connectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'connected_by_uid', 'firebase_uid');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by', 'firebase_uid');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by', 'firebase_uid');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForUser(Builder $query, string $firebaseUid): Builder
    {
        return $query->where(function (Builder $subQuery) use ($firebaseUid) {
            $subQuery->where('user_a_firebase_uid', $firebaseUid)
                ->orWhere('user_b_firebase_uid', $firebaseUid);
        });
    }

    public function scopeBetween(Builder $query, string $userAUid, string $userBUid): Builder
    {
        return $query->where(function (Builder $betweenQuery) use ($userAUid, $userBUid) {
            $betweenQuery->where(function (Builder $subQuery) use ($userAUid, $userBUid) {
                $subQuery->where('user_a_firebase_uid', $userAUid)
                    ->where('user_b_firebase_uid', $userBUid);
            })->orWhere(function (Builder $subQuery) use ($userAUid, $userBUid) {
                $subQuery->where('user_a_firebase_uid', $userBUid)
                    ->where('user_b_firebase_uid', $userAUid);
            });
        });
    }

    public function scopeForTeamMembers(Builder $query, array $memberUids): Builder
    {
        return $query->where(function (Builder $subQuery) use ($memberUids) {
            $subQuery->whereIn('user_a_firebase_uid', $memberUids)
                ->orWhereIn('user_b_firebase_uid', $memberUids);
        });
    }

    public function scopeCanonicalPair(Builder $query, string $repUid, string $raterUid): Builder
    {
        return $query->where('user_a_firebase_uid', $repUid)
            ->where('user_b_firebase_uid', $raterUid);
    }
}
