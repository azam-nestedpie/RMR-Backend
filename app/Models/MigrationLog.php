<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MigrationLog extends Model
{
    protected $table = 'migration_logs';

    protected $fillable = [
        'entity_type',
        'collection',
        'firestore_doc_id',
        'old_id',
        'new_id',
        'status',
        'error_message',
        'raw_data',
        'migrated_by',
        'migrated_at',
    ];

    protected $casts = [
        'raw_data' => 'array',
        'migrated_at' => 'datetime',
    ];

    public function scopeFailed($q)
    {
        return $q->where('status', 'failed');
    }
}
