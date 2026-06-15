<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RatingEdit extends Model
{
    protected $fillable = [
        'rating_id',
        'rater_firebase_uid',
        'rep_firebase_uid',
        'previous_average_score',
        'new_average_score',
        'previous_items',
        'new_items',
        'edited_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'previous_average_score' => 'float',
            'new_average_score' => 'float',
            'previous_items' => 'array',
            'new_items' => 'array',
            'edited_at' => 'datetime',
        ];
    }

    public function rating(): BelongsTo
    {
        return $this->belongsTo(Rating::class);
    }

    public function rater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rater_firebase_uid', 'firebase_uid');
    }

    public function rep(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rep_firebase_uid', 'firebase_uid');
    }
}
