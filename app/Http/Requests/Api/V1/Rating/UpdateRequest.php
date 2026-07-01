<?php

namespace App\Http\Requests\Api\V1\Rating;

use App\Http\Requests\Api\V1\V1Request;
use App\Models\Rating;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Validator;

class UpdateRequest extends V1Request
{
    public function authorize(): bool
    {
        return $this->user()?->can('ratings.submit') === true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.question_id' => ['required', 'integer', 'distinct', 'exists:rating_questions,id'],
            'items.*.score' => ['required', 'numeric', 'min:1', 'max:5'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $ratingUuid = $this->route('ratingUuid');
                $rating = Rating::where('firebase_uuid', $ratingUuid)->first();
                $repUid = $rating?->rep_firebase_uid;

                if (! $repUid) {
                    $validator->errors()->add('rating', 'Rating not found.');

                    return;
                }

                $industryId = DB::table('user_industries')
                    ->where('user_firebase_uid', $repUid)
                    ->value('industry_id');

                if (! $industryId) {
                    $validator->errors()->add('industry', 'Representative does not have an industry assigned.');

                    return;
                }

                $questionIds = collect($this->input('items', []))
                    ->pluck('question_id')
                    ->map(fn (mixed $questionId): int => (int) $questionId)
                    ->unique()
                    ->values();

                $allowedQuestionIds = DB::table('industry_rating_questions')
                    ->where('industry_id', $industryId)
                    ->whereIn('question_id', $questionIds)
                    ->pluck('question_id')
                    ->map(fn (mixed $questionId): int => (int) $questionId);

                if ($questionIds->diff($allowedQuestionIds)->isNotEmpty()) {
                    $validator->errors()->add(
                        'items',
                        'One or more submitted questions are not configured for the selected industry.'
                    );
                }
            },
        ];
    }
}
