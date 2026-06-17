<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\StoreInviteRequest;
use App\Http\Resources\InviteResource;
use App\Models\Invite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

class InviteController extends Controller
{
    /**
     * List invites with their restaurant and creator/redeemer.
     */
    public function index(): AnonymousResourceCollection
    {
        $invites = Invite::with(['restaurant', 'creator', 'usedBy'])
            ->latest()
            ->get();

        return InviteResource::collection($invites);
    }

    /**
     * Generate a new single-use invite.
     */
    public function store(StoreInviteRequest $request): InviteResource
    {
        $invite = Invite::create([
            'code' => Str::random(16),
            'restaurant_id' => $request->validated('restaurant_id'),
            'role' => $request->validated('role'),
            'created_by' => $request->user()->id,
            'expires_at' => $request->validated('expires_at'),
        ]);

        return new InviteResource($invite->load(['restaurant', 'creator']));
    }

    /**
     * Revoke an unused invite.
     */
    public function destroy(Request $request, Invite $invite): JsonResponse
    {
        if ($invite->used_at !== null) {
            return response()->json([
                'message' => 'This invite has already been used and cannot be revoked.',
            ], 422);
        }

        $invite->delete();

        return response()->json(status: 204);
    }
}
