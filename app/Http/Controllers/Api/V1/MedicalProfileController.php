<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateMedicalProfileRequest;
use App\Http\Resources\V1\UserMedicalProfileResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MedicalProfileController extends Controller
{
    /**
     * Show the authenticated user's medical profile. Returns null if empty.
     */
    public function show(Request $request): UserMedicalProfileResource|JsonResponse
    {
        $profile = $request->user()->medicalProfile()->first();

        return $profile === null
            ? response()->json(['data' => null])
            : new UserMedicalProfileResource($profile);
    }

    /**
     * Update or create the authenticated user's medical profile.
     */
    public function update(UpdateMedicalProfileRequest $request): JsonResponse
    {
        $profile = $request->user()->medicalProfile()->updateOrCreate(
            ['user_id' => $request->user()->id],
            $request->validatedMedicalAttributes(),
        );

        return (new UserMedicalProfileResource($profile))
            ->response()
            ->setStatusCode(200);
    }
}
