<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Call\DeleteDeviceTokenRequest;
use App\Http\Requests\Call\StoreDeviceTokenRequest;
use App\Http\Resources\DeviceTokenResource;
use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DeviceTokenController extends Controller
{
    /**
     * List all registered device tokens for the authenticated user.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        $tokens = $user->deviceTokens()->latest('last_used_at')->get();

        return DeviceTokenResource::collection($tokens);
    }

    /**
     * Register or update an FCM device token for the authenticated user.
     */
    public function store(StoreDeviceTokenRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $validated = $request->validated();

        // Atomically reassign or create token for current user
        $deviceToken = DeviceToken::query()->firstOrNew(['token' => $validated['token']]);
        $isNew = ! $deviceToken->exists;

        $deviceToken->fill([
            'user_id' => $user->id,
            'device_id' => $validated['device_id'] ?? null,
            'device_type' => $validated['device_type'],
            'device_name' => $validated['device_name'] ?? null,
            'is_active' => true,
            'last_used_at' => now(),
        ]);
        $deviceToken->save();

        return (new DeviceTokenResource($deviceToken))
            ->response()
            ->setStatusCode($isNew ? 201 : 200);
    }

    /**
     * Invalidate or remove an FCM device token for the authenticated user.
     */
    public function destroy(DeleteDeviceTokenRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $token = $request->validated('token');

        $deviceToken = $user->deviceTokens()->where('token', $token)->first();

        if ($deviceToken) {
            $deviceToken->delete();
        }

        return response()->json([
            'message' => 'Device token removed successfully.',
        ]);
    }
}
