<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Call;
use App\Services\Call\CallLifecycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CallTokenController extends Controller
{
    /**
     * Generate LiveKit room credentials for an authorized participant.
     */
    public function token(Request $request, Call $call, CallLifecycleService $lifecycleService): JsonResponse
    {
        $credentials = $lifecycleService->getParticipantToken($call, $request->user());

        return response()->json($credentials);
    }
}
