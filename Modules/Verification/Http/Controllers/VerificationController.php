<?php

namespace Modules\Verification\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Verification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class VerificationController extends Controller
{
    public function start()
    {
        $user = auth()->user();

        return response()->json([
            'message' => 'Adult verification required',
            'steps' => [
                ['step' => 1, 'action' => 'upload_id', 'required' => true],
                ['step' => 2, 'action' -> 'liveness_check', 'required' => true],
                ['step' => 3, 'action' => 'biometric_binding', 'required' => true],
            ],
            'current_step' => $user->is_verified_adult ? 'complete' : 1,
        ]);
    }

    public function uploadId(Request $request)
    {
        $validated = $request->validate([
            'document_front' => 'required|file|mimes:png,jpg,jpeg|max:10240',
            'document_back' => 'nullable|file|mimes:png,jpg,jpeg|max:10240',
            'document_type' => 'required|string|in:passport,drivers_license,national_id',
            'date_of_birth' => 'required|date',
        ]);

        if (now()->parse($validated['date_of_birth'])->age < 18) {
            return response()->json(['error' => 'Must be 18 or older'], 422);
        }

        $frontPath = $request->file('document_front')->store('verification');
        $backPath = $validated['document_back'] ? $request->file('document_back')->store('verification') : null;

        $verification = Verification::create([
            'user_id' => auth()->id(),
            'method' => Verification::METHOD_ID_UPLOAD,
            'status' => Verification::STATUS_PENDING,
            'document_type' => $validated['document_type'],
            'document_url' => $frontPath,
            'document_verified_url' => $backPath,
            'age_verified' => false,
            'verification_provider' => 'internal',
        ]);

        // Update user DOB
        auth()->user()->update(['date_of_birth' => $validated['date_of_birth']]);

        return response()->json([
            'success' => true,
            'verification_id' => $verification->id,
            'next_step' => 'liveness_check',
        ]);
    }

    public function livenessCheck(Request $request)
    {
        $validated = $request->validate([
            'video' => 'required|file|mimes:mp4,mov|max:51200',
            'challenge_response' => 'required|string',
        ]);

        $videoPath = $request->file('video')->store('verification');

        // Process liveness check
        $livenessScore = $this->processLivenessCheck($videoPath, $validated['challenge_response']);

        Verification::where('user_id', auth()->id())
            ->latest()
            ->first()
            ?->update([
                'method' => Verification::METHOD_LIVENESS,
                'liveness_score' => $livenessScore,
            ]);

        if ($livenessScore >= 0.85) {
            return response()->json([
                'success' => true,
                'liveness_passed' => true,
                'score' => $livenessScore,
                'next_step' => 'biometric_binding',
            ]);
        }

        return response()->json([
            'error' => 'Liveness check failed',
            'score' => $livenessScore,
            'threshold' => 0.85,
        ], 422);
    }

    public function biometricBinding(Request $request)
    {
        $validated = $request->validate([
            'biometric_token' => 'required|string',
            'device_id' => 'required|string',
        ]);

        // Bind biometric to device
        $device = auth()->user()->devices()->where('device_id', $validated['device_id'])->first();

        if ($device) {
            $device->update([
                'biometric_enabled' => true,
                'biometric_type' => 'passkey',
            ]);
        }

        // Complete verification
        $verification = Verification::where('user_id', auth()->id())
            ->latest()
            ->first();

        if ($verification) {
            $verification->update([
                'method' => Verification::METHOD_BIOMETRIC,
                'status' => Verification::STATUS_APPROVED,
                'biometric_data' => encrypt(json_encode([
                    'token' => $validated['biometric_token'],
                    'device_id' => $validated['device_id'],
                ])),
                'age_verified' => true,
            ]);

            auth()->user()->update([
                'is_verified_adult' => true,
                'verification_status' => 'approved',
                'verified_at' => now(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Verification complete - full access granted',
        ]);
    }

    public function status()
    {
        $user = auth()->user();

        return response()->json([
            'is_verified' => $user->is_verified_adult,
            'status' => $user->verification_status,
            'verified_at' => $user->verified_at,
        ]);
    }

    protected function processLivenessCheck(string $videoPath, string $challengeResponse): float
    {
        // AI-powered liveness detection
        return 0.95; // Placeholder
    }
}
