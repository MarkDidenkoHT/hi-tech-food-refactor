<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreMenuItemRequest;
use App\Http\Requests\Api\UpdateMenuItemRequest;
use App\Http\Resources\MenuItemResource;
use App\Models\MenuItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MenuItemController extends Controller
{
    /**
     * List active menu items for a restaurant.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $restaurantId = (int) $request->query('restaurant_id');

        abort_unless($restaurantId > 0 && $request->user()->belongsToRestaurant($restaurantId), 403);

        $items = MenuItem::where('restaurant_id', $restaurantId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return MenuItemResource::collection($items);
    }

    /**
     * Add a new menu item.
     */
    public function store(StoreMenuItemRequest $request): MenuItemResource
    {
        $data = $request->validated();

        $nextSortOrder = (int) MenuItem::where('restaurant_id', $data['restaurant_id'])->max('sort_order') + 1;

        $item = MenuItem::create([
            'restaurant_id' => $data['restaurant_id'],
            'name' => $data['name'],
            'sort_order' => $nextSortOrder,
            'is_active' => true,
        ]);

        return new MenuItemResource($item);
    }

    /**
     * Update a menu item.
     */
    public function update(UpdateMenuItemRequest $request, MenuItem $menuItem): MenuItemResource
    {
        $menuItem->update($request->validated());

        return new MenuItemResource($menuItem);
    }

    /**
     * Delete a menu item.
     */
    public function destroy(Request $request, MenuItem $menuItem): JsonResponse
    {
        abort_unless($request->user()->belongsToRestaurant($menuItem->restaurant_id), 403);

        $menuItem->delete();

        return response()->json(null, 204);
    }
}
