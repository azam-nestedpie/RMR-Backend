<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\IndustryResource;
use App\Http\Resources\Api\V1\RatingQuestionResource;
use App\Models\Industry;
use Illuminate\Http\JsonResponse;

class IndustryController extends Controller
{
    public function index(): JsonResponse
    {
        $industries = Industry::all();

        return response()->json([
            'success' => true,
            'data' => IndustryResource::collection($industries),
        ]);
    }

    public function ratingQuestions(Industry $industry): JsonResponse
    {
        $questions = $industry->ratingQuestions()
            ->where('rating_questions.is_active', true)
            ->get();

        return response()->json([
            'success' => true,
            'data' => RatingQuestionResource::collection($questions),
        ]);
    }
}
