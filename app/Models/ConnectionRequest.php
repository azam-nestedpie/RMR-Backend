<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ConnectionRequest extends Model
{
    protected $fillable = [
        'firebase_uuid',
        'requester_firebase_uid',
        'target_user_firebase_uid',
        'manager_firebase_uid',
        'behalf_firebase_uid',
        'status_id',
        'created_by',
        'updated_by',
    ];

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_firebase_uid', 'firebase_uid');
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_firebase_uid', 'firebase_uid');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_firebase_uid', 'firebase_uid');
    }

    public function behalfUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'behalf_firebase_uid', 'firebase_uid');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class, 'status_id');
    }

    public function connection(): HasOne
    {
        return $this->hasOne(Connection::class, 'source_request_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by', 'firebase_uid');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by', 'firebase_uid');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereHas('status', fn (Builder $statusQuery) => $statusQuery->where('name', 'pending'));
    }

    public function scopeForTarget(Builder $query, string $firebaseUid): Builder
    {
        return $query->where('target_user_firebase_uid', $firebaseUid);
    }

    public function scopeForRequester(Builder $query, string $firebaseUid): Builder
    {
        return $query->where('requester_firebase_uid', $firebaseUid);
    }

    public function scopeBetweenParticipants(Builder $query, string $userAUid, string $userBUid): Builder
    {
        return $query->where(function (Builder $betweenQuery) use ($userAUid, $userBUid) {
            $betweenQuery->where(function (Builder $subQuery) use ($userAUid, $userBUid) {
                $subQuery->where('requester_firebase_uid', $userAUid)
                    ->where('target_user_firebase_uid', $userBUid);
            })->orWhere(function (Builder $subQuery) use ($userAUid, $userBUid) {
                $subQuery->where('requester_firebase_uid', $userBUid)
                    ->where('target_user_firebase_uid', $userAUid);
            });
        });
    }
}
