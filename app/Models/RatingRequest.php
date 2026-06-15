<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RatingRequest extends Model
{
    protected $fillable = [
        'firebase_uuid',
        'requester_firebase_uid',
        'target_user_firebase_uid',
        'manager_firebase_uid',
        'behalf_firebase_uid',
        'requested_by_manager_firebase_uid',
        'rater_firebase_uid',
        'subject_rep_firebase_uid',
        'requested_by_role_id',
        'status_id',
        'requested_at',
        'responded_at',
        'completed_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'responded_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function rater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rater_firebase_uid', 'firebase_uid');
    }

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

    public function subjectRep(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subject_rep_firebase_uid', 'firebase_uid');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class, 'status_id');
    }

    public function requesterRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'requested_by_role_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by', 'firebase_uid');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by', 'firebase_uid');
    }
}
