<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\OtpVerifyRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    public function __construct(private readonly OtpService $otpService)
    {
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'username'     => $request->username,
            'email'        => $request->email,
            'phone'        => $request->phone,
            'password'     => $request->password,
            'display_name' => $request->username,
            'country_code' => $request->country_code,
            'coin_balance' => (int) config('wallet.welcome_bonus_coins', 100),
        ]);

        $token = JWTAuth::fromUser($user);

        return response()->json([
            'user'  => $user->toProfileArray(),
            'token' => $token,
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->only('email', 'password');

        if (! $token = JWTAuth::attempt($credentials)) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        $user = auth()->user();

        if (! $user->is_active) {
            JWTAuth::invalidate();
            return response()->json(['message' => 'Account is disabled.'], 403);
        }

        $user->update(['last_seen_at' => now()]);

        return response()->json([
            'user'  => $user->toProfileArray(),
            'token' => $token,
        ]);
    }

    public function sendOtp(Request $request): JsonResponse
    {
        $request->validate(['phone' => 'required|string|max:20']);

        $this->otpService->send($request->phone);

        return response()->json(['message' => 'OTP sent successfully.']);
    }

    public function verifyOtp(OtpVerifyRequest $request): JsonResponse
    {
        if (! $this->otpService->verify($request->phone, $request->code)) {
            return response()->json(['message' => 'Invalid or expired OTP.'], 422);
        }

        $user = User::firstOrCreate(
            ['phone' => $request->phone],
            [
                'username'     => 'user_'.Str::random(8),
                'display_name' => 'King User',
                'password'     => Str::random(32),
                'coin_balance' => (int) config('wallet.welcome_bonus_coins', 100),
            ]
        );

        $token = JWTAuth::fromUser($user);

        return response()->json([
            'user'        => $user->toProfileArray(),
            'token'       => $token,
            'is_new_user' => $user->wasRecentlyCreated,
        ]);
    }

    public function refresh(): JsonResponse
    {
        return response()->json(['token' => JWTAuth::refresh()]);
    }

    public function logout(): JsonResponse
    {
        JWTAuth::invalidate();

        return response()->json(['message' => 'Logged out successfully.']);
    }

    public function me(): JsonResponse
    {
        return response()->json(auth()->user()->toProfileArray());
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $data = $request->validate([
            'display_name' => ['sometimes', 'string', 'max:100'],
            'bio'          => ['sometimes', 'string', 'max:500'],
            'avatar_url'   => ['sometimes', 'url'],
            'frame_url'    => ['sometimes', 'url'],
        ]);

        auth()->user()->update($data);

        return response()->json(auth()->user()->fresh()->toProfileArray());
    }

    public function updateDeviceToken(Request $request): JsonResponse
    {
        $request->validate(['device_token' => 'required|string']);

        auth()->user()->update(['device_token' => $request->device_token]);

        return response()->json(['message' => 'Device token updated.']);
    }
}
