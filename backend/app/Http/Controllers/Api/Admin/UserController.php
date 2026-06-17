<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserController extends Controller
{
    /**
     * List users, optionally filtered by restaurant or role.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = User::query()->with('restaurants')->orderBy('first_name');

        if ($request->filled('restaurant_id')) {
            $query->whereHas('restaurants', function ($q) use ($request) {
                $q->where('restaurants.id', $request->integer('restaurant_id'));
            });
        }

        if ($request->filled('role')) {
            $query->where('role', $request->string('role'));
        }

        return UserResource::collection($query->get());
    }

    /**
     * Update a user's role, active status, or restaurant memberships.
     */
    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        $data = $request->validated();

        $user->update(collect($data)->only(['role', 'is_active'])->all());

        if (array_key_exists('restaurant_ids', $data)) {
            $user->restaurants()->sync($data['restaurant_ids']);
        }

        return new UserResource($user->load('restaurants'));
    }

    /**
     * Delete a user.
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($user->id === $request->user()->id) {
            return response()->json([
                'message' => 'You cannot delete your own account.',
            ], 422);
        }

        $user->delete();

        return response()->json(status: 204);
    }
}
