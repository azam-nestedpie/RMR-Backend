<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class RatingQuestion extends Model
{
    protected $fillable = [
        'question_code',
        'title_en',
        'title_es',
        'title_pt',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function industries(): BelongsToMany
    {
        return $this->belongsToMany(Industry::class, 'industry_rating_questions', 'question_id', 'industry_id')
            ->withPivot(['display_order', 'is_required'])
            ->withTimestamps();
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
