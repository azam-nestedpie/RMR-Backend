<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManagerTeamMember extends Model
{
    protected $fillable = [
        'manager_firebase_uid',
        'member_firebase_uid',
        'manager_type_role_id',
        'active',
        'joined_at',
        'left_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'active' => 'boolean',
        'joined_at' => 'datetime',
        'left_at' => 'datetime',
    ];

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_firebase_uid', 'firebase_uid');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'member_firebase_uid', 'firebase_uid');
    }

    public function managerType(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'manager_type_role_id');
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
