<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MetricSnapshot extends Model
{
    protected $fillable = [
        'snapshot_date',
        'scope_type',
        'scope_user_firebase_uid',
        'metric_key',
        'metric_value',
        'metric_json',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'snapshot_date' => 'date',
        'metric_value' => 'decimal:2',
        'metric_json' => 'json',
    ];

    public function scopeUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scope_user_firebase_uid', 'firebase_uid');
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
