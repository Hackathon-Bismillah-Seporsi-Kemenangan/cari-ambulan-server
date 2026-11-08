<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Requests\Api\V1\RegisterRequest;
use App\Http\Resources\V1\UserResource;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Mobile authentication with Sanctum personal access tokens.
 */
class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();

        try {
            return DB::transaction(function () use ($data): JsonResponse {
                $user = User::create([
                    'full_name' => $data['full_name'],
                    'email' => $data['email'],
                    'phone' => $data['phone'],
                    'alamat' => $data['alamat'] ?? '-',
                    'password' => $data['password'],
                ]);

                $role = Role::firstOrCreate(['name' => $data['role']]);
                $user->roles()->attach($role);

                return response()->json([
                    'token' => $user->createToken($data['device_name'])->plainTextToken,
                    'user' => new UserResource($user->load('roles')),
                ], 201);
            });
        } catch (UniqueConstraintViolationException $exception) {
            if (! User::where('email', $data['email'])->exists()) {
                throw $exception;
            }

            throw ValidationException::withMessages([
                'email' => 'Email ini sudah terdaftar. Silakan masuk.',
            ]);
        }
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::with('roles')->where('email', $request->string('email')->toString())->first();

        if ($user === null || ! Hash::check($request->string('password')->toString(), $user->password)) {
            throw ValidationException::withMessages([
                'email' => 'Email atau kata sandi yang Anda masukkan salah.',
            ]);
        }

        return response()->json([
            'token' => $user->createToken($request->string('device_name')->toString())->plainTextToken,
            'user' => new UserResource($user),
        ]);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user()->load('roles'));
    }

    public function logout(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }
}

