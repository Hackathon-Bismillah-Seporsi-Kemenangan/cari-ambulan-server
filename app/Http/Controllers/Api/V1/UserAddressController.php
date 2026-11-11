<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreUserAddressRequest;
use App\Http\Resources\V1\UserAddressResource;
use App\Models\UserAddress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserAddressController extends Controller
{
    /**
     * List all saved addresses for the authenticated user.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $addresses = $request->user()->addresses()->latest()->get();

        return UserAddressResource::collection($addresses);
    }

    /**
     * Save a new address for the authenticated user.
     */
    public function store(StoreUserAddressRequest $request): JsonResponse
    {
        $address = $request->user()->addresses()->create($request->validated());

        return (new UserAddressResource($address))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Delete an address belonging to the authenticated user.
     */
    public function destroy(Request $request, UserAddress $userAddress): JsonResponse
    {
        abort_if($userAddress->user_id !== $request->user()->id, 404);

        $userAddress->delete();

        return response()->json(['message' => 'Alamat berhasil dihapus.']);
    }
}
