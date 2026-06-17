<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StorePhotoQuestionRequest;
use App\Http\Requests\Api\UpdatePhotoQuestionRequest;
use App\Http\Resources\PhotoQuestionResource;
use App\Models\PhotoQuestion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PhotoQuestionController extends Controller
{
    /**
     * List active photo task prompts for a restaurant.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $restaurantId = (int) $request->query('restaurant_id');

        abort_unless($restaurantId > 0 && $request->user()->belongsToRestaurant($restaurantId), 403);

        $questions = PhotoQuestion::where('restaurant_id', $restaurantId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return PhotoQuestionResource::collection($questions);
    }

    /**
     * Add a new photo task prompt.
     */
    public function store(StorePhotoQuestionRequest $request): PhotoQuestionResource
    {
        $data = $request->validated();

        $nextSortOrder = (int) PhotoQuestion::where('restaurant_id', $data['restaurant_id'])->max('sort_order') + 1;

        $question = PhotoQuestion::create([
            'restaurant_id' => $data['restaurant_id'],
            'question' => $data['question'],
            'sort_order' => $nextSortOrder,
            'is_active' => true,
        ]);

        return new PhotoQuestionResource($question);
    }

    /**
     * Update a photo task prompt.
     */
    public function update(UpdatePhotoQuestionRequest $request, PhotoQuestion $photoQuestion): PhotoQuestionResource
    {
        $photoQuestion->update($request->validated());

        return new PhotoQuestionResource($photoQuestion);
    }

    /**
     * Delete a photo task prompt.
     */
    public function destroy(Request $request, PhotoQuestion $photoQuestion): JsonResponse
    {
        abort_unless($request->user()->belongsToRestaurant($photoQuestion->restaurant_id), 403);

        $photoQuestion->delete();

        return response()->json(null, 204);
    }
}
