<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Industry extends Model
{
    protected $fillable = [
        'name',
        'created_by',
        'updated_by',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_industries', 'industry_id', 'user_firebase_uid')
            ->withPivot('is_primary');
    }

    public function ratingQuestions(): BelongsToMany
    {
        return $this->belongsToMany(RatingQuestion::class, 'industry_rating_questions', 'industry_id', 'question_id')
            ->withPivot(['display_order', 'is_required'])
            ->withTimestamps()
            ->orderByPivot('display_order');
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
