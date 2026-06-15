<?php

namespace App\Services\V1;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Repositories\Contracts\RatingRepositoryInterface;
use Illuminate\Support\Facades\Log;

class RaterProfileService
{
    public function __construct(
        private readonly RatingRepositoryInterface $ratings,
    ) {}

    public function show(User $rep, string $raterUid): array
    {
        Log::info('Rater profile requested', ['rep_uid' => $rep->firebase_uid, 'rater_uid' => $raterUid]);

        $rater = User::query()
            ->select(['firebase_uid', 'first_name', 'last_name', 'company_name', 'position', 'bio', 'email', 'image_url'])
            ->where('firebase_uid', $raterUid)
            ->first();

        if (! $rater) {
            throw ApiException::notFound('Rater not found.');
        }

        $ratings = $this->ratings->byRaterAndRep($raterUid, $rep->firebase_uid);

        return [
            'rater' => $rater,
            'ratings' => $ratings,
        ];
    }
}
