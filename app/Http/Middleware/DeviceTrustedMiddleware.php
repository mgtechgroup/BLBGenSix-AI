<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Device;

class DeviceTrustedMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (!Auth::check()) {
            return $next($request);
        }

        $fingerprint = $this->calculateFingerprint($request);
        
        $device = Device::where('fingerprint', $fingerprint)
            ->where('user_id', Auth::id())
            ->first();

        // Register device if not exists
        if (!$device) {
            $maxDevices = config('app.adult_verification.max_devices_per_user', 3);
            $currentDevices = Device::where('user_id', Auth::id())->count();

            if ($currentDevices >= $maxDevices) {
                return response()->json([
                    'error' => 'Maximum device limit reached',
                    'max_devices' => $maxDevices,
                    'message' => 'Remove a device from your profile before adding a new one'
                ], 403);
            }

            Device::create([
                'user_id' => Auth::id(),
                'device_id' => $request->header('X-Device-ID', uniqid('dev_')),
                'fingerprint' => $fingerprint,
                'device_name' => $request->header('X-Device-Name', 'Unknown Device'),
                'platform' => $request->header('X-Platform'),
                'browser' => $request->header('X-Browser'),
                'os' => $request->header('X-OS'),
                'os_version' => $request->header('X-OS-Version'),
                'last_ip' => $request->ip(),
                'last_user_agent' => $request->header('User-Agent'),
                'is_trusted' => false,
                'is_primary' => $currentDevices === 0,
                'biometric_enabled' => false,
                'registered_at' => now(),
                'last_seen_at' => now(),
            ]);

            return response()->json([
                'error' => 'New device detected - trust verification required',
                'device_id' => $request->header('X-Device-ID'),
                'redirect' => route('devices.trust')
            ], 403);
        }

        return $next($request);
    }

    private function calculateFingerprint(Request $request): string
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
            $request->header('X-Audio-Context', ''),
            $request->header('X-Fonts', ''),
        ];

        return hash('sha256', implode('|', $components));
    }
}
