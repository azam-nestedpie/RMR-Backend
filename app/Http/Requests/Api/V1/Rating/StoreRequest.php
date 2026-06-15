<?php

namespace App\Http\Requests\Api\V1\Rating;

use App\Http\Requests\Api\V1\V1Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Validator;

class StoreRequest extends V1Request
{
    public function authorize(): bool
    {
        return $this->user()?->can('ratings.submit') === true;
    }

    protected function prepareForValidation(): void
    {
        $ratedUserUid = $this->input('receiver_uid') ?? $this->input('rep_uid');

        if ($ratedUserUid) {
            $this->merge(['rated_user_uid' => $ratedUserUid]);
        }
    }

    public function rules(): array
    {
        return [
            'rated_user_uid' => ['required', 'string', 'exists:users,firebase_uid'],
            'rep_uid' => ['nullable', 'string', 'exists:users,firebase_uid'],
            'rater_uid' => ['nullable', 'string', 'exists:users,firebase_uid'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.question_id' => ['required', 'integer', 'distinct', 'exists:rating_questions,id'],
            'items.*.score' => ['required', 'numeric', 'min:1', 'max:5'],
            'rating_request_id' => ['nullable', 'string', 'exists:rating_requests,firebase_uuid'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $user = $this->user();
                $industryId = $user?->industries()?->first()?->id;

                if (! $industryId) {
                    $validator->errors()->add('industry', 'User does not have an industry assigned.');

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
