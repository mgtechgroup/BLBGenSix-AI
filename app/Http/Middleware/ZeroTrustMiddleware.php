<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Device;
use App\Models\Verification;

class ZeroTrustMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Authentication required'], 401);
        }

        $user = Auth::user();

        // Always re-verify user on every request
        if ($user->is_banned) {
            return response()->json(['error' => 'Account banned', 'reason' => $user->ban_reason], 403);
        }

        if (!$user->is_verified_adult) {
            return response()->json([
                'error' => 'Adult verification required',
                'redirect' => route('verification.required')
            ], 403);
        }

        // Check device trust
        $fingerprint = $this->getDeviceFingerprint($request);
        $device = Device::where('fingerprint', $fingerprint)
            ->where('user_id', $user->id)
            ->first();

        if (!$device || !$device->is_trusted) {
            return response()->json([
                'error' => 'Untrusted device',
                'redirect' => route('devices.register')
            ], 403);
        }

        // Verify biometric session hasn't expired
        $sessionRotation = config('app.zero_trust.session_rotation', 900);
        if ($device->last_seen_at < now()->subSeconds($sessionRotation)) {
            return response()->json([
                'error' => 'Session expired - biometric re-verification required',
                'requires_biometric' => true,
                'redirect' => route('biometric.reverify')
            ], 401);
        }

        // IP validation
        if (config('app.zero_trust.ip_validation')) {
            $currentIp = $request->ip();
            if ($device->last_ip && $device->last_ip !== $currentIp) {
                return response()->json([
                    'error' => 'IP address changed - re-verification required',
                    'requires_biometric' => true
                ], 401);
            }
        }

        // Update last seen
        $device->update([
            'last_seen_at' => now(),
            'last_ip' => $request->ip(),
        ]);

        return $next($request);
    }

    private function getDeviceFingerprint(Request $request): string
    {
        $components = [
            $request->header('User-Agent', ''),
            $request->header('X-Device-Canvas', ''),
            $request->header('X-Device-WebGL', ''),
            $request->header('X-Screen-Resolution', ''),
            $request->header('X-Timezone', ''),
            $request->header('X-Language', ''),
            $request->header('X-Platform', ''),
            $request->header('X-CPU-Cores', ''),
            $request->header('X-Memory', ''),
        ];

        return hash('sha256', implode('|', $components));
    }
}
