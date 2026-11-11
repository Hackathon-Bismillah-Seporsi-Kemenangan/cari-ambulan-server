<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateProfileRequest;
use App\Http\Resources\V1\UserResource;
use Illuminate\Http\Request;

class MeController extends Controller
{
    /**
     * Show the authenticated user's profile.
     */
    public function show(Request $request): UserResource
    {
        return new UserResource($request->user()->load('roles'));
    }

    /**
     * Update the authenticated user's name and phone number.
     */
    public function update(UpdateProfileRequest $request): UserResource
    {
        $user = $request->user();
        $user->update($request->validated());

        return new UserResource($user->load('roles'));
    }
}
