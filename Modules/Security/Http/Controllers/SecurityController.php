<?php

namespace Modules\Security\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\Verification;
use Illuminate\Http\Request;

class SecurityController extends Controller
{
    public function myDevices()
    {
        return response()->json([
            'devices' => auth()->user()->devices()->orderBy('is_primary', 'desc')->get(),
            'max_devices' => config('app.adult_verification.max_devices_per_user', 3),
        ]);
    }

    public function removeDevice($id)
    {
        $device = Device::where('user_id', auth()->id())->findOrFail($id);

        if ($device->is_primary) {
            return response()->json(['error' => 'Cannot remove primary device'], 400);
        }

        $device->delete();

        return response()->json(['success' => true]);
    }

    public function trustDevice(Request $request)
    {
        $validated = $request->validate([
            'device_id' => 'required|string',
            'biometric_token' => 'required|string',
        ]);

        $device = Device::where('user_id', auth()->id())
            ->where('device_id', $validated['device_id'])
            ->firstOrFail();

        $device->update([
            'is_trusted' => true,
            'biometric_enabled' => true,
            'biometric_type' => 'passkey',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Device trusted with biometric authentication',
        ]);
    }

    public function verificationStatus()
    {
        $user = auth()->user();

        return response()->json([
            'is_verified_adult' => $user->is_verified_adult,
            'verification_status' => $user->verification_status,
            'verified_at' => $user->verified_at,
            'verification_method' => $user->verification()->latest()->first()?->method,
        ]);
    }

    public function sessionInfo()
    {
        return response()->json([
            'session_rotation' => config('app.zero_trust.session_rotation'),
            'ip_validation' => config('app.zero_trust.ip_validation'),
            'device_binding' => config('app.zero_trust.device_binding'),
            'biometric_required' => config('app.zero_trust.biometric_required'),
            'current_ip' => request()->ip(),
            'user_agent' => request()->header('User-Agent'),
        ]);
    }

    public function auditLog()
    {
        $activities = auth()->user()
            ->activities()
            ->latest()
            ->paginate(50);

        return response()->json($activities);
    }
}
