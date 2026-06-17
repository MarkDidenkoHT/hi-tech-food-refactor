<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\StoreRestaurantRequest;
use App\Http\Requests\Api\Admin\UpdateRestaurantRequest;
use App\Http\Resources\RestaurantResource;
use App\Models\Restaurant;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

class RestaurantController extends Controller
{
    /**
     * List all restaurants.
     */
    public function index(): AnonymousResourceCollection
    {
        return RestaurantResource::collection(Restaurant::orderBy('name')->get());
    }

    /**
     * Create a new restaurant.
     */
    public function store(StoreRestaurantRequest $request): RestaurantResource
    {
        $data = $request->validated();
        $data['slug'] ??= Str::slug($data['name']);
        $data['is_active'] = true;

        $restaurant = Restaurant::create($data);

        return new RestaurantResource($restaurant);
    }

    /**
     * Update a restaurant (including activating/deactivating it).
     */
    public function update(UpdateRestaurantRequest $request, Restaurant $restaurant): RestaurantResource
    {
        $restaurant->update($request->validated());

        return new RestaurantResource($restaurant);
    }
}
