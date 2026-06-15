<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Team extends Model
{
    protected $table = 'team_requests';

    protected $fillable = [
        'firebase_uuid',
        'manager_firebase_uid',
        'target_user_firebase_uid',
        'manager_type_role_id',
        'status_id',
        'responded_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'responded_at' => 'datetime',
        ];
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_firebase_uid', 'firebase_uid');
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_firebase_uid', 'firebase_uid');
    }

    public function managerRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'manager_type_role_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class, 'status_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'firebase_uid');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by', 'firebase_uid');
    }

    public function scopeForTarget(Builder $query, string $firebaseUid): Builder
    {
        return $query->where('target_user_firebase_uid', $firebaseUid);
    }

    public function scopeForManager(Builder $query, string $firebaseUid): Builder
    {
        return $query->where('manager_firebase_uid', $firebaseUid);
    }
}
