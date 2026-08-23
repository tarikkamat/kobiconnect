<?php

declare(strict_types=1);

namespace App\Http\Controllers\Communication;

use App\Actions\Communication\Ai\AnalyzeProductReviews;
use App\Actions\Communication\Ai\AutoAnswerCustomerQuestion;
use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiQuestionReviewController extends Controller
{
    public function answerQuestion(Request $request, AutoAnswerCustomerQuestion $action): JsonResponse
    {
        $validated = $request->validate([
            'question' => ['required', 'string', 'max:2000'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
        ]);

        $productId = isset($validated['product_id']) ? (int) $validated['product_id'] : null;
        $product = $productId !== null
            ? Product::with(['variants', 'category', 'brand'])->where('id', $productId)->first()
            : null;

        $result = $action($validated['question'], $product);

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    public function analyzeReviews(Request $request, AnalyzeProductReviews $action): JsonResponse
    {
        $validated = $request->validate([
            'reviews' => ['required', 'array', 'min:1'],
            'reviews.*.rating' => ['required', 'integer', 'min:1', 'max:5'],
            'reviews.*.comment' => ['required', 'string'],
            'reviews.*.date' => ['nullable', 'string'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
        ]);

        $productId = isset($validated['product_id']) ? (int) $validated['product_id'] : null;
        $product = $productId !== null
            ? Product::where('id', $productId)->first()
            : null;

        /** @var array<int, array{rating: int, comment: string, date?: string}> $reviews */
        $reviews = $validated['reviews'];
        $result = $action($reviews, $product);

        return response()->json([
            'success' => true,
            'analysis' => $result,
        ]);
    }
}
