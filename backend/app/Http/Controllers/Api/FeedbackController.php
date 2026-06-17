<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use App\Services\Feedback\WooCommerceFeedbackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeedbackController extends Controller
{
    public function __construct(
        private readonly WooCommerceFeedbackService $feedbackService,
    ) {}

    /**
     * Show customer feedback and order reviews for a restaurant.
     */
    public function index(Request $request): JsonResponse
    {
        $restaurantId = (int) $request->query('restaurant_id');

        abort_unless($restaurantId > 0 && $request->user()->belongsToRestaurant($restaurantId), 403);

        $restaurant = Restaurant::findOrFail($restaurantId);

        if ($restaurant->woocommerce_domain === null) {
            return response()->json([
                'available' => false,
                'feedbacks' => [],
                'order_reviews' => [],
            ]);
        }

        return response()->json([
            'available' => true,
            'feedbacks' => $this->feedbackService->getFeedbacks($restaurant->woocommerce_domain),
            'order_reviews' => $this->feedbackService->getOrderReviews($restaurant->woocommerce_domain),
        ]);
    }
}
