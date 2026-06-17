<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;

class MeController extends Controller
{
    /**
     * Return the currently authenticated user.
     */
    public function show(Request $request): UserResource
    {
        return new UserResource($request->user()->load('restaurants'));
    }
}
