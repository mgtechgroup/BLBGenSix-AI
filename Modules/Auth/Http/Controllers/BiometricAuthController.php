<?php

namespace Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Device;
use App\Models\Verification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Asbiin\LaravelWebauthn\Facades\Webauthn;

class BiometricAuthController extends Controller
{
    public function register()
    {
        return inertia('Auth/Register', [
            'options' => Webauthn::authenticateOptions(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email|unique:users',
            'username' => 'required|string|min:3|max:50|unique:users',
            'date_of_birth' => 'required|date|before:' . now()->subYears(18)->format('Y-m-d'),
        ]);

        if (now()->parse($validated['date_of_birth'])->age < 18) {
            return response()->json([
                'error' => 'You must be 18 or older to register'
            ], 422);
        }

        $user = User::create([
            'email' => $validated['email'],
            'username' => $validated['username'],
            'date_of_birth' => $validated['date_of_birth'],
            'is_verified_adult' => false,
            'verification_status' => 'pending',
            'subscription_status' => 'trial',
            'trial_ends_at' => now()->addDays(7),
            'api_usage_count' => 0,
            'api_usage_limit' => 10,
            'credits_remaining' => 50,
        ]);

        // Assign default role
        $user->assignRole('user');

        return response()->json([
            'message' => 'Registration successful',
            'user_id' => $user->id,
            'next_step' => 'biometric_registration',
        ]);
    }

    public function login()
    {
        return inertia('Auth/Login', [
            'options' => Webauthn::authenticateOptions(),
        ]);
    }

    public function authenticate(Request $request)
    {
        // WebAuthn handles the actual authentication
        // This is called after successful biometric verification
        if (!Auth::check()) {
            return response()->json(['error' => 'Biometric authentication failed'], 401);
        }

        $user = Auth::user();

        // Check adult verification
        if (!$user->is_verified_adult) {
            return response()->json([
                'error' => 'Adult verification required',
                'redirect' => route('verification.start')
            ], 403);
        }

        // Check subscription
        if ($user->subscription_status === 'none' || $user->subscription_status === 'expired') {
            return response()->json([
                'error' => 'No active subscription',
                'redirect' => route('billing.plans')
            ], 403);
        }

        $token = $user->createToken('device-token', ['*'], now()->addMinutes(15))->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user->only(['id', 'username', 'email', 'subscription_plan']),
            'redirect' => route('dashboard'),
        ]);
    }

    public function registerBiometric(Request $request)
    {
        $request->validate([
            'credential' => 'required|array',
        ]);

        $user = Auth::user() ?? User::findOrFail($request->input('user_id'));

        $credential = Webauthn::register($user, $request->input('credential'));

        return response()->json([
            'message' => 'Biometric registered successfully',
            'credential_id' => $credential->credential_id,
        ]);
    }
}
