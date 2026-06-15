<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DashboardExport extends Model
{
    protected $fillable = [
        'requested_by_firebase_uid',
        'scope_type',
        'scope_user_firebase_uid',
        'filters_json',
        'file_url',
        'status_id',
        'requested_at',
        'completed_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'filters_json' => 'json',
        'requested_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_firebase_uid', 'firebase_uid');
    }

    public function scopeUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scope_user_firebase_uid', 'firebase_uid');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class, 'status_id');
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
