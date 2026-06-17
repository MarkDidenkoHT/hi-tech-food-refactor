<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreEventRequest;
use App\Http\Requests\Api\UpdateEventRequest;
use App\Http\Resources\EventResource;
use App\Models\Event;
use App\Models\Restaurant;
use App\Services\Calendar\WooCommerceReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function __construct(
        private readonly WooCommerceReservationService $reservationService,
    ) {}

    /**
     * List calendar events for a restaurant: local events plus any read-only
     * WooCommerce table reservations.
     */
    public function index(Request $request): JsonResponse
    {
        $restaurantId = (int) $request->query('restaurant_id');

        abort_unless($restaurantId > 0 && $request->user()->belongsToRestaurant($restaurantId), 403);

        $restaurant = Restaurant::findOrFail($restaurantId);

        $events = Event::where('restaurant_id', $restaurantId)
            ->orderBy('event_date')
            ->orderBy('event_time')
            ->get();

        $local = EventResource::collection($events)->toArray($request);

        $reservations = $restaurant->woocommerce_domain !== null
            ? $this->reservationService->getReservations($restaurant->woocommerce_domain)
            : [];

        return response()->json([
            'data' => array_merge($local, $reservations),
        ]);
    }

    /**
     * Create a new local event.
     */
    public function store(StoreEventRequest $request): EventResource
    {
        $data = $request->validated();

        $event = Event::create([
            'restaurant_id' => $data['restaurant_id'],
            'event_type' => $data['event_type'] ?? null,
            'title' => $data['title'],
            'event_date' => $data['event_date'],
            'event_time' => $data['event_time'] ?? null,
            'guests' => $data['guests'] ?? 0,
            'contact' => $data['contact'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        return new EventResource($event);
    }

    /**
     * Update a local event.
     */
    public function update(UpdateEventRequest $request, Event $event): EventResource
    {
        $event->update($request->validated());

        return new EventResource($event);
    }

    /**
     * Delete a local event.
     */
    public function destroy(Request $request, Event $event): JsonResponse
    {
        abort_unless($request->user()->belongsToRestaurant($event->restaurant_id), 403);

        $event->delete();

        return response()->json(null, 204);
    }
}
