<?php

namespace App\Http\Requests\Api\V1\ExternalRatingRequest;

use App\Http\Requests\Api\V1\V1Request;
use App\Models\ExternalRatingRequest as ExternalRatingRequestModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SubmitRequest extends V1Request
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $invitation = $this->invitation();

        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => [
                'required',
                'email',
                'max:191',
                $invitation ? Rule::in([$invitation->email]) : Rule::in([]),
            ],
            'company_name' => ['required', 'string', 'max:255'],
            'position' => ['required', 'string', 'max:255'],
            'ratings' => ['required', 'array', 'size:10'],
            'ratings.*.question_id' => ['required', 'integer', 'distinct', 'exists:rating_questions,id'],
            'ratings.*.score' => ['required', 'numeric', 'min:1', 'max:5'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $email = $this->input('email');

        if (is_string($email)) {
            $this->merge([
                'email' => mb_strtolower(trim($email)),
            ]);
        }
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $invitation = $this->invitation();
                if (! $invitation) {
                    $validator->errors()->add('uuid', 'Invitation not found.');

                    return;
                }

                $submittedQuestionIds = collect($this->input('ratings', []))
                    ->pluck('question_id')
                    ->map(fn (mixed $questionId): int => (int) $questionId)
                    ->sort()
                    ->values();

                $allowedQuestionIds = DB::table('rating_questions')
                    ->where('is_active', true)
                    ->orderBy('question_code')
                    ->limit(10)
                    ->pluck('id')
                    ->map(fn (mixed $questionId): int => (int) $questionId)
                    ->sort()
                    ->values();

                if ($submittedQuestionIds->count() !== 10 || $submittedQuestionIds->diff($allowedQuestionIds)->isNotEmpty()) {
                    $validator->errors()->add('ratings', 'You must submit exactly the 10 configured rating questions.');
                }
            },
        ];
    }

    public function invitation(): ?ExternalRatingRequestModel
    {
        $invitation = $this->route('externalRatingRequest');

        return $invitation instanceof ExternalRatingRequestModel ? $invitation : null;
    }
}
