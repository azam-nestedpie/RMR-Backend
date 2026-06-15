<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExternalRatingRequest extends Model
{
    protected $fillable = [
        'invite_uuid',
        'rep_id',
        'email',
        'token',
        'expires_at',
        'used_at',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    public function getRouteKeyName(): string
    {
        return 'invite_uuid';
    }

    public function salesRep(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rep_id', 'firebase_uid');
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
