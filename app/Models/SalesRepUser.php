<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesRepUser extends Model
{
    protected $table = 'sales_rep_users';

    protected $primaryKey = 'user_firebase_uid';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'user_firebase_uid',
        'avg_rating',
        'ratings_count',
        'is_subscribed',
        'subscription_started_at',
        'subscription_expires_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'avg_rating' => 'float',
        'ratings_count' => 'integer',
        'is_subscribed' => 'boolean',
        'subscription_started_at' => 'datetime',
        'subscription_expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_firebase_uid', 'firebase_uid');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by', 'firebase_uid');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by', 'firebase_uid');
    }

    /**
     * Client Engagement Rate: (# ratings submitted / # requests sent)
     */
    public function getEngagementRateAttribute(): float
    {
        $requests = RatingRequest::where('subject_rep_firebase_uid', $this->user_firebase_uid)->count();
        if ($requests === 0) {
            return 0;
        }

        $ratings = Rating::where('rep_firebase_uid', $this->user_firebase_uid)
            ->whereNotNull('rating_request_id')
            ->count();

        return round(($ratings / $requests) * 100, 2);
    }

    /**
     * Resolution Rate: track follow-up ratings > 3 after < 3
     */
    public function getResolutionRateAttribute(): float
    {
        $initialFailures = Rating::where('rep_firebase_uid', $this->user_firebase_uid)
            ->where('average_score', '<', 3.0)
            ->get();

        if ($initialFailures->isEmpty()) {
            return 100.0;
        }

        $resolved = 0;
        foreach ($initialFailures as $fail) {
            $hasFollowUp = Rating::where('rep_firebase_uid', $this->user_firebase_uid)
                ->where('rater_firebase_uid', $fail->rater_firebase_uid)
                ->where('created_at', '>', $fail->created_at)
                ->where('average_score', '>=', 3.0)
                ->exists();
            if ($hasFollowUp) {
                $resolved++;
            }
        }

        return round(($resolved / $initialFailures->count()) * 100, 2);
    }
}
